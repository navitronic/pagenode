<?php

declare(strict_types=1);

namespace Pagenode;

use DateTimeImmutable;

/** Node Class - each Node instance represents a single file */
class Node
{
    /** @var string[] */
    public static array $DebugOpenedNodes = [];

    protected ?string $body = null;

    /**
     * @param array<string, mixed> $meta
     * @param string[] $tags
     * @param callable $bodyLoader
     */
    public function __construct(
        private string $path,
        public string $keyword,
        public array $tags,
        protected array $meta,
        public DateTimeImmutable|int $date,
        protected bool $raw,
        private $bodyLoader
    ) {
    }

    public function hasTag(string $tag): bool
    {
        return in_array($tag, $this->tags, true);
    }

    public function __get(string $name): mixed
    {
        if ($name === 'body') {
            if ($this->body === null) {
                $this->body = $this->loadBody();
            }
            return $this->body;
        }

        return $this->meta[$name] ?? null;
    }

    protected function loadBody(): string
    {
        self::$DebugOpenedNodes[] = $this->path;
        $loader = $this->bodyLoader;
        return $loader();
    }
}
