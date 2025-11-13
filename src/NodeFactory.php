<?php

declare(strict_types=1);

namespace Pagenode;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Pagenode\Content\BodyRenderer;
use Pagenode\Content\MarkdownDocumentLoader;
use function htmlspecialchars;

final class NodeFactory
{
    public function __construct(
        private MarkdownDocumentLoader $documentLoader,
        private BodyRenderer $bodyRenderer
    ) {
    }

    /**
     * @param array<string, mixed> $meta
     */
    public function create(string $path, array $meta, bool $raw = false): Node
    {
        $keyword = (string) (pathinfo($path, PATHINFO_FILENAME) ?: '');
        $meta = $this->ensureMetaShape($meta);
        $tags = $raw ? $meta['tags'] : $this->sanitizeTags($meta['tags']);
        $date = $raw
            ? $this->normaliseRawDate($meta['date'])
            : $this->createDateTime($meta['date']);

        $bodyLoader = function () use ($path, $raw) {
            $document = $this->documentLoader->load($path);
            $body = $document->body();
            return $raw ? $body : $this->bodyRenderer->render($body);
        };

        if (!$raw) {
            $meta['tags'] = $tags;
        }

        $processedMeta = $raw
            ? $meta
            : $this->sanitizeMeta($meta);

        return new Node(
            $path,
            $keyword,
            $tags,
            $processedMeta,
            $date,
            $raw,
            $bodyLoader
        );
    }

    /**
     * @param array<string, mixed> $meta
     * @return array<string, mixed>
     */
    private function ensureMetaShape(array $meta): array
    {
        $meta['tags'] = isset($meta['tags']) && is_array($meta['tags'])
            ? $meta['tags']
            : [];
        $meta['tags'] = array_values(array_map(static fn ($tag) => (string) $tag, $meta['tags']));
        $meta['date'] = $meta['date'] ?? time();

        return $meta;
    }

    /**
     * @param array<int, string> $tags
     * @return string[]
     */
    private function sanitizeTags(array $tags): array
    {
        return array_map(static fn ($tag) => htmlspecialchars((string) $tag), $tags);
    }

    /**
     * @param array<string, mixed> $meta
     * @return array<string, mixed>
     */
    private function sanitizeMeta(array $meta): array
    {
        foreach ($meta as $key => $value) {
            if (is_string($value)) {
                $meta[$key] = htmlspecialchars($value);
            }
        }

        return $meta;
    }

    private function createDateTime(mixed $dateValue): DateTimeImmutable
    {
        if ($dateValue instanceof DateTimeImmutable) {
            return $dateValue;
        }

        if ($dateValue instanceof DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($dateValue);
        }

        if (is_int($dateValue) || (is_string($dateValue) && ctype_digit($dateValue))) {
            return $this->dateFromTimestamp((int) $dateValue);
        }

        if (is_string($dateValue) && $dateValue !== '') {
            try {
                return new DateTimeImmutable($dateValue);
            } catch (\Exception) {
                // noop, fall through to fallback below.
            }
        }

        return new DateTimeImmutable();
    }

    private function dateFromTimestamp(int $timestamp): DateTimeImmutable
    {
        $timezone = new DateTimeZone(date_default_timezone_get());
        return (new DateTimeImmutable('@' . $timestamp))->setTimezone($timezone);
    }

    private function normaliseRawDate(mixed $dateValue): int
    {
        if ($dateValue instanceof DateTimeInterface) {
            return $dateValue->getTimestamp();
        }

        if (is_int($dateValue) || (is_string($dateValue) && ctype_digit($dateValue))) {
            return (int) $dateValue;
        }

        if (is_string($dateValue) && $dateValue !== '') {
            $timestamp = strtotime($dateValue);
            if ($timestamp !== false) {
                return $timestamp;
            }
        }

        if (is_numeric($dateValue)) {
            return (int) $dateValue;
        }

        return time();
    }
}
