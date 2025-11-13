<?php

namespace Pagenode;

use Parsedown;

/** Node Class - each Node instance represents a single file */
class Node
{
    public static $DebugOpenedNodes = [];

    /**
     * @var mixed[]|string
     */
    public $keyword;
    public $tags = [];
    public $date;
    protected array $meta;
    protected $body;

    public function __construct(protected $path, $keyword, array $meta, protected $raw = false)
    {
        $this->keyword = pathinfo((string) $this->path, PATHINFO_FILENAME);
        $this->date = $this->raw ? $meta['date'] : new DateTime($meta['date']);
        $this->meta = $meta;

        if (!$this->raw) {
            foreach ($meta['tags'] as $t) {
                $this->tags[] = htmlspecialchars((string) $t);
            }
        } else {
            $this->tags = $meta['tags'];
        }
    }

    protected function loadBody()
    {
        self::$DebugOpenedNodes[] = $this->path;
        $markdown = FileReader::ReadContent($this->path);

        if ($this->raw) {
            return $markdown;
        } else {
            return PN_SYNTAX_HIGHLIGHT_LANGS === '' || PN_SYNTAX_HIGHLIGHT_LANGS === '0'
                ? Parsedown::instance()->text($markdown)
                : ParsedownSyntaxHighlight::instance()->text($markdown);
        }
    }

    public function hasTag($tag): bool
    {
        return in_array($tag, $this->meta['tags']);
    }

    public function __get($name)
    {
        if ($name === 'body') {
            if (!$this->body) {
                $this->body = $this->loadBody();
            }
            return $this->body;
        } elseif (isset($this->meta[$name])) {
            return $this->raw
                ? $this->meta[$name]
                : htmlspecialchars((string) $this->meta[$name]);
        }

        return null;
    }
}
