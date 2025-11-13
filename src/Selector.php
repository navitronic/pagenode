<?php

declare(strict_types=1);

namespace Pagenode;

use Pagenode\Content\BodyRenderer;
use Pagenode\Content\FrontMatterParser;
use Pagenode\Content\MarkdownDocumentLoader;
use Pagenode\Storage\IndexCache;
use Symfony\Component\Finder\Finder;
use function htmlspecialchars;

/**
 * Selector - provides a query interface for Nodes on the filesystem
 */
class Selector
{
    /**
     * @var array<int, array<string, mixed>>
     */
    public static array $DebugInfo = [];

    protected ?string $path = null;

    protected static int $FoundNodes = 0;

    private MarkdownDocumentLoader $documentLoader;

    private NodeFactory $nodeFactory;

    private IndexCache $indexCache;

    public const SORT_DESC = 'desc';
    public const SORT_ASC = 'asc';

    public function __construct(
        string $path,
        ?MarkdownDocumentLoader $documentLoader = null,
        ?NodeFactory $nodeFactory = null,
        ?IndexCache $indexCache = null
    ) {
        $resolvedPath = realpath('./' . $path . '/');
        if (!$resolvedPath || strpos($path, '..') !== false) {
            header('HTTP/1.1 500 Internal Error');
            echo 'select("' . htmlspecialchars($path) . '") does not exist.';
            exit();
        }
        $this->path = $resolvedPath;

        $this->documentLoader = $documentLoader ?? new MarkdownDocumentLoader(new FrontMatterParser());
        $bodyRenderer = new BodyRenderer();
        $this->nodeFactory = $nodeFactory ?? new NodeFactory($this->documentLoader, $bodyRenderer);

        $cacheDir = defined('PN_CACHE_INDEX_PATH') && PN_CACHE_INDEX_PATH
            ? rtrim((string) PN_CACHE_INDEX_PATH, DIRECTORY_SEPARATOR)
            : null;
        $this->indexCache = $indexCache ?? new IndexCache($cacheDir);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    protected function rebuildIndex(): array
    {
        if ($this->path === null) {
            return [];
        }

        $index = [];
        foreach ($this->createFinder() as $file) {
            $realPath = $file->getRealPath();
            if ($realPath === false) {
                continue;
            }

            $document = $this->documentLoader->load($realPath);
            $meta = $document->meta();

            if (($meta['active'] ?? true) === false) {
                continue;
            }

            $keyword = pathinfo($file->getFilename(), PATHINFO_FILENAME);
            if ($keyword === false) {
                continue;
            }

            $index[$keyword] = $meta;
        }

        if (empty($index)) {
            return $index;
        }

        uasort($index, static function (array $a, array $b): int {
            return ($b['date'] ?? 0) <=> ($a['date'] ?? 0);
        });

        return $index;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    protected function getIndex(): array
    {
        if ($this->path === null) {
            return [];
        }

        $timeStart = microtime(true);
        $version = $this->currentIndexVersion();
        $cacheResult = $this->indexCache->get(
            $this->path,
            $version,
            fn () => $this->rebuildIndex()
        );

        self::$DebugInfo[] = [
            'action' => 'loadIndex',
            'path' => $this->path,
            'ms' => round((microtime(true) - $timeStart) * 1000, 3),
            'didRebuild' => (int) $cacheResult['rebuilt'],
            'cacheMethod' => $this->usesIndicatorFile()
                ? 'INDICATOR_FILE'
                : 'NODE_LAST_MODIFIED'
        ];

        return $cacheResult['data'];
    }

    public static function FoundNodes(): int
    {
        return self::$FoundNodes;
    }

    /**
     * @param array<string, mixed> $params
     */
    public function one(array $params = [], bool $raw = false): ?Node
    {
        $nodes = $this->query('date', self::SORT_DESC, 1, $params, $raw);
        return $nodes[0] ?? null;
    }

    /**
     * @param array<string, mixed> $params
     * @return Node[]
     */
    public function newest(int $count = 0, array $params = [], bool $raw = false): array
    {
        return $this->query('date', self::SORT_DESC, $count, $params, $raw);
    }

    /**
     * @param array<string, mixed> $params
     * @return Node[]
     */
    public function oldest(int $count = 0, array $params = [], bool $raw = false): array
    {
        return $this->query('date', self::SORT_ASC, $count, $params, $raw);
    }

    /**
     * @param array<string, mixed> $params
     * @return Node[]
     */
    public function query(string $sort, string $order, int $count, array $params, bool $raw = false): array
    {
        if (!$this->path) {
            return [];
        }

        $index = $this->getIndex();

        $timeStart = microtime(true);
        $scannedNodes = count($index);

        $index = $this->filterByKeyword($index, $params);
        $index = $this->filterByDate($index, $params);
        $index = $this->filterByTags($index, $params);
        $index = $this->filterByMeta($index, $params);
        $index = $this->filterByCallback($index, $params);

        $index = $this->sortIndex($index, $sort, $order);

        self::$FoundNodes = count($index);
        $index = $this->paginateIndex($index, $count, $params);

        $nodes = [];
        foreach ($index as $keyword => $meta) {
            $nodePath = $this->path . '/' . $keyword . '.md';
            $nodes[] = $this->nodeFactory->create($nodePath, $meta, $raw);
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

    /**
     * @param array<string, array<string, mixed>> $index
     * @param array<string, mixed> $params
     * @return array<string, array<string, mixed>>
     */
    private function filterByKeyword(array $index, array $params): array
    {
        if (empty($params['keyword'])) {
            return $index;
        }

        $keyword = (string) $params['keyword'];
        return !empty($index[$keyword]) ? [$keyword => $index[$keyword]] : [];
    }

    /**
     * @param array<string, array<string, mixed>> $index
     * @param array<string, mixed> $params
     * @return array<string, array<string, mixed>>
     */
    private function filterByDate(array $index, array $params): array
    {
        if (empty($params['date'])) {
            return $index;
        }

        $y = is_array($params['date'])
            ? ($params['date'][0] ?? null)
            : $params['date'];
        $m = is_array($params['date']) ? ($params['date'][1] ?? null) : null;
        $d = is_array($params['date']) ? ($params['date'][2] ?? null) : null;
        if (is_string($y) && preg_match('/(\d{4}).(\d{2}).(\d{2})/', $y, $match)) {
            $y = $match[1];
            $m = $match[2];
            $d = $match[3];
        }

        $year = (int) ($y ?: date('Y'));
        $month = (int) ($m ?: 1);
        $day = (int) ($d ?: 1);
        $endMonth = $m ? (int) $m : 12;
        $endDay = $d ? (int) $d : 31;
        $start = mktime(0, 0, 0, $month, $day, $year);
        $end = mktime(23, 59, 59, $endMonth, $endDay, $year);

        return array_filter($index, static function (array $n) use ($start, $end) {
            return ($n['date'] ?? 0) >= $start && ($n['date'] ?? 0) <= $end;
        });
    }

    /**
     * @param array<string, array<string, mixed>> $index
     * @param array<string, mixed> $params
     * @return array<string, array<string, mixed>>
     */
    private function filterByTags(array $index, array $params): array
    {
        if (empty($params['tags'])) {
            return $index;
        }

        $tags = !is_array($params['tags'])
            ? array_map('trim', explode(',', (string) $params['tags']))
            : $params['tags'];

        return array_filter($index, static function (array $n) use ($tags) {
            return !array_udiff($tags, $n['tags'] ?? [], 'strcasecmp');
        });
    }

    /**
     * @param array<string, array<string, mixed>> $index
     * @param array<string, mixed> $params
     * @return array<string, array<string, mixed>>
     */
    private function filterByMeta(array $index, array $params): array
    {
        if (empty($params['meta']) || !is_array($params['meta'])) {
            return $index;
        }
        $meta = $params['meta'];

        return array_filter($index, static function (array $n) use ($meta) {
            foreach ($meta as $key => $value) {
                if (!array_key_exists($key, $n) || $n[$key] !== $value) {
                    return false;
                }
            }
            return true;
        });
    }

    /**
     * @param array<string, array<string, mixed>> $index
     * @param array<string, mixed> $params
     * @return array<string, array<string, mixed>>
     */
    private function filterByCallback(array $index, array $params): array
    {
        if (empty($params['filter']) || !is_callable($params['filter'])) {
            return $index;
        }

        return array_filter($index, $params['filter']);
    }

    /**
     * @param array<string, array<string, mixed>> $index
     */
    private function sortIndex(array $index, string $sort, string $order): array
    {
        if ($sort === 'date' && $order === self::SORT_DESC) {
            return $index;
        }

        uasort($index, static function (array $a, array $b) use ($sort, $order) {
            if ($order === self::SORT_ASC) {
                return ($a[$sort] ?? INF) <=> ($b[$sort] ?? INF);
            }

            return ($b[$sort] ?? 0) <=> ($a[$sort] ?? 0);
        });

        return $index;
    }

    /**
     * @param array<string, array<string, mixed>> $index
     * @param array<string, mixed> $params
     */
    private function paginateIndex(array $index, int $count, array $params): array
    {
        if ($count <= 0) {
            return $index;
        }

        $page = (int) ($params['page'] ?? 0);
        $offset = $page * $count;
        return array_slice($index, $offset, $count, true);
    }

    private function currentIndexVersion(): string
    {
        if ($this->path === null) {
            return '0';
        }

        if ($this->usesIndicatorFile()) {
            $indicatorTime = $this->indicatorFileTime();
            return 'indicator:' . $indicatorTime;
        }

        $latest = 0;
        foreach ($this->createFinder() as $file) {
            $latest = max($latest, $file->getMTime());
        }

        return 'mtime:' . $latest;
    }

    private function usesIndicatorFile(): bool
    {
        return defined('PN_CACHE_USE_INDICATOR_FILE') && PN_CACHE_USE_INDICATOR_FILE;
    }

    private function indicatorFileTime(): int
    {
        $indicatorFile = defined('PN_CACHE_INDICATOR_FILE')
            ? PN_CACHE_INDICATOR_FILE
            : null;

        if (!$indicatorFile || !file_exists($indicatorFile)) {
            return 0;
        }

        $time = filemtime($indicatorFile);
        return $time !== false ? $time : 0;
    }

    private function createFinder(): Finder
    {
        $finder = new Finder();
        if ($this->path) {
            $finder
                ->files()
                ->in($this->path)
                ->depth('== 0')
                ->name('*.md');
        }

        return $finder;
    }
}
