<?php

declare(strict_types=1);

namespace Pagenode\Content;

/**
 * @psalm-type Metadata=array<string, mixed>
 */
final class MarkdownDocument
{
    /**
     * @param array<string, mixed> $meta
     */
    public function __construct(
        private array $meta,
        private string $body
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function meta(): array
    {
        return $this->meta;
    }

    public function body(): string
    {
        return $this->body;
    }
}
