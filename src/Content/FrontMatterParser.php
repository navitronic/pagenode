<?php

declare(strict_types=1);

namespace Pagenode\Content;

use Spatie\YamlFrontMatter\YamlFrontMatter;

final class FrontMatterParser
{
    public function parse(string $contents, string $path): MarkdownDocument
    {
        $document = YamlFrontMatter::parse($contents);
        $meta = $document->matter();

        if (!is_array($meta)) {
            $meta = [];
        }

        $meta['tags'] = $this->normalizeTags($meta['tags'] ?? []);
        $meta['date'] = $this->normalizeDate($meta['date'] ?? null, $path);
        $meta['active'] = $this->normalizeActive($meta['active'] ?? null);

        return new MarkdownDocument($meta, $document->body());
    }

    /**
     * @param mixed $tags
     * @return string[]
     */
    private function normalizeTags(mixed $tags): array
    {
        if (is_string($tags)) {
            $tags = array_map('trim', explode(',', $tags));
        } elseif (!is_array($tags)) {
            $tags = [];
        }

        return array_values(
            array_filter(
                array_map(static fn ($tag) => (string) $tag, $tags),
                static fn ($tag) => $tag !== ''
            )
        );
    }

    /**
     * @param mixed $date
     */
    private function normalizeDate(mixed $date, string $path): int
    {
        if (
            !empty($date) &&
            is_string($date) &&
            preg_match(
                '/(\d{4})[\.\-](\d{2})[\.\-](\d{2})(?:\s+(\d{2}):(\d{2}))?/',
                $date,
                $match
            )
        ) {
            $year = (int) $match[1];
            $month = (int) $match[2];
            $day = (int) $match[3];
            $hour = !empty($match[4]) ? (int) $match[4] : 0;
            $minute = !empty($match[5]) ? (int) $match[5] : 0;
            $timestamp = mktime($hour, $minute, 0, $month, $day, $year);
            if ($timestamp !== false) {
                return $timestamp;
            }
        }

        if (is_numeric($date)) {
            return (int) $date;
        }

        $mtime = @filemtime($path);
        return $mtime !== false ? $mtime : time();
    }

    /**
     * @param mixed $active
     */
    private function normalizeActive(mixed $active): bool
    {
        if (is_bool($active)) {
            return $active;
        }

        if (is_string($active)) {
            return strtolower($active) !== 'false';
        }

        return empty($active) || $active !== 'false';
    }
}
