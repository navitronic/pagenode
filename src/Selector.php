<?php

namespace Pagenode;

/**
 * Selector - provides a query interface for Nodes on the filesystem
 */
class Selector
{
    public static $DebugInfo = [];

    protected $path = null;
    protected $indexPath = null;
    protected static $IndexCache = [];
    protected static $FoundNodes = 0;

    public const SORT_DESC = 'desc';
    public const SORT_ASC = 'asc';

    public function __construct($path)
    {
        $this->path = realpath('./' . $path . '/');
        if (!$this->path || strstr($path, '..') !== false) {
            header("HTTP/1.1 500 Internal Error");
            echo 'select("' . htmlSpecialChars($path) . '") does not exist.';
            exit();
        }
        $this->indexPath =
            (PN_CACHE_INDEX_PATH ?? sys_get_temp_dir()) .
            '/pagenode-index-' . md5($this->path) . '.json';
    }

    protected function rebuildIndex()
    {
        $index = [];
        foreach (glob($this->path . '/*.md') as $path) {
            $meta = $this->loadMetaFromFile($path);
            if ($meta['active'] !== false) {
                $keyword = pathInfo($path, PATHINFO_FILENAME);
                $index[$keyword] = $meta;
            }
        }

        if (empty($index)) {
            return $index;
        }

        uasort($index, function ($a, $b) {
            return $b['date'] <=> $a['date'];
        });

        $jsonOpts = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
        $json = json_encode($index, $jsonOpts);
        file_put_contents($this->indexPath, $json);

        return $index;
    }

    protected function indexIsCurrent()
    {
        if (!file_exists($this->indexPath)) {
            return false;
        }

        $indexTime = filemtime($this->indexPath);
        if (
            PN_CACHE_USE_INDICATOR_FILE &&
            file_exists(PN_CACHE_INDICATOR_FILE)
        ) {
            return $indexTime > filemtime(PN_CACHE_INDICATOR_FILE);
        }

        $lastFileTime = 0;
        foreach (glob($this->path . '/*.md') as $f) {
            $lastFileTime = max($lastFileTime, filemtime($f));
        }
        return $indexTime > $lastFileTime;
    }

    protected function getIndex()
    {
        $timeStart = microtime(true);
        $didRebuild = false;

        if (!isset(self::$IndexCache[$this->path])) {
            if ($this->indexIsCurrent()) {
                $json = file_get_contents($this->indexPath);
                self::$IndexCache[$this->path] = json_decode($json, true);
            } else {
                self::$IndexCache[$this->path] = $this->rebuildIndex();
                $didRebuild = true;
            }

            self::$DebugInfo[] = [
                'action' => 'loadIndex',
                'path' => $this->path,
                'indexPath' => $this->indexPath,
                'ms' => round((microtime(true) - $timeStart) * 1000, 3),
                'didRebuild' => (int)$didRebuild,
                'cacheMethod' => PN_CACHE_USE_INDICATOR_FILE
                    ? 'INDICATOR_FILE'
                    : 'NODE_LAST_MODIFIED'
            ];
        }

        return self::$IndexCache[$this->path] ?? [];
    }

    protected function loadMetaFromFile($path)
    {
        return FileReader::ReadMeta($path);
    }


    public static function FoundNodes()
    {
        return self::$FoundNodes;
    }

    public function one($params = [], $raw = false)
    {
        $nodes = $this->query('date', self::SORT_DESC, 1, $params, $raw);
        return !empty($nodes) ? $nodes[0] : null;
    }

    public function newest($count = 0, $params = [], $raw = false)
    {
        return $this->query('date', self::SORT_DESC, $count, $params, $raw);
    }

    public function oldest($count = 0, $params = [], $raw = false)
    {
        return $this->query('date', self::SORT_ASC, $count, $params, $raw);
    }

    public function query($sort, $order, $count, $params, $raw = false)
    {
        if (!$this->path) {
            return [];
        }

        $index = $this->getIndex();

        $timeStart = microtime(true);
        $scannedNodes = count($index);

        // Filter by keyword. Since keywords are unique, we can simply index
        // by it, returning only one node.

        if (!empty($params['keyword'])) {
            $index = !empty($index[$params['keyword']])
                ? [$params['keyword'] => $index[$params['keyword']]]
                : [];
        }


        // Filter by date. Allow to become more granual by specifying either
        // just year, year & month or year & month & day.

        if (!empty($params['date'])) {
            $y = $params['date'][0] ?? $params['date'];
            $m = $params['date'][1] ?? null;
            $d = $params['date'][2] ?? null;
            if (preg_match('/(\d{4}).(\d{2}).(\d{2})/', $y, $match)) {
                $y = $match[1];
                $m = $match[2];
                $d = $match[3];
            }
            $start = mktime(0, 0, 0, ($m ? $m : 1), ($d ? $d : 1), $y);
            $end = mktime(23, 59, 59, ($m ? $m : 12), ($d ? $d : 31), $y);

            $index = array_filter($index, function ($n) use ($start, $end) {
                return $n['date'] >= $start && $n['date'] <= $end;
            });
        }


        // Filter by tags. Only return nodes that match all given tags.

        if (!empty($params['tags'])) {
            $tags = !is_array($params['tags'])
                ? array_map('trim', explode(',', $params['tags']))
                : $params['tags'];
            $index = array_filter($index, function ($n) use ($tags) {
                return !array_udiff($tags, $n['tags'], 'strcasecmp');
            });
        }


        // Filter by arbitrary properties

        if (!empty($params['meta'])) {
            $meta = $params['meta'];
            $index = array_filter($index, function ($n) use ($meta) {
                foreach ($meta as $key => $value) {
                    if (!isset($n[$key]) || $n[$key] !== $value) {
                        return false;
                    }
                }
                return true;
            });
        }

        // Filter using a custom filter function

        if (!empty($params['filter']) && is_callable($params['filter'])) {
            $index = array_filter($index, $params['filter']);
        }


        // Sort by any property

        if ($sort === 'date' && $order === self::SORT_DESC) {
            // Nothing to do here; index is sorted by date, desc by default
        } else {
            if ($order === self::SORT_ASC) {
                uasort($index, function ($a, $b) use ($sort) {
                    return ($a[$sort] ?? INF) <=> ($b[$sort] ?? INF);
                });
            } else {
                uasort($index, function ($a, $b) use ($sort) {
                    return ($b[$sort] ?? 0) <=> ($a[$sort] ?? 0);
                });
            }
        }

        // Keep track of the total nodes found with the given filter params

        self::$FoundNodes = count($index);

        // Slice and Paginate

        if ($count) {
            $offset = ($params['page'] ?? 0) * $count;
            $index = array_slice($index, $offset, $count, true);
        }


        // Create Nodes

        $nodes = [];
        foreach ($index as $keyword => $meta) {
            $nodePath = $this->path . '/' . $keyword . '.md';
            $nodes[] = new Node($nodePath, $keyword, $meta, $raw);
        }

        self::$DebugInfo[] = [
            'action' => 'query',
            'path' => $this->path,
            'ms' => round((microtime(true) - $timeStart) * 1000, 3),
            'scanned' => $scannedNodes,
            'returned' => count($nodes),
            'params' => $params
        ];

        return $nodes;
    }
}
