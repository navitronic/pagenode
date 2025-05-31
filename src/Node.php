<?php

namespace Phoboslab\Pagenode;

/** Node Class - each Node instance represents a single file */
class Node
{
    public static $DebugOpenedNodes = [];

    public $keyword;
    public $tags = [];
    public $date;
    protected $path;
    protected $meta = [];
    protected $body = null;
    protected $raw = false;

    public function __construct($path, $keyword, $meta, $raw = false)
    {
        $this->raw = $raw;
        $this->path = $path;
        $this->keyword = pathInfo($path, PATHINFO_FILENAME);
        $this->date = $raw ? $meta['date'] : new DateTime($meta['date']);
        $this->meta = $meta;

        if (!$raw) {
            foreach ($meta['tags'] as $t) {
                $this->tags[] = htmlSpecialChars($t);
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
            return !empty(PN_SYNTAX_HIGHLIGHT_LANGS)
                ? ParsedownSyntaxHighlight::instance()->text($markdown)
                : \Parsedown::instance()->text($markdown);
        }
    }

    public function hasTag($tag)
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
                : htmlSpecialChars($this->meta[$name]);
        }

        return null;
    }
}
