<?php

declare(strict_types=1);

namespace Pagenode;

use DateTimeInterface;

/**
 * DateTime class - a simple wrapper for timestamps
 */
class DateTime
{
    protected int $timestamp;

    public function __construct(DateTimeInterface|int|string $timestamp)
    {
        $this->timestamp = $this->normalizeTimestamp($timestamp);
    }

    public function format(?string $format = null): string
    {
        $resolvedFormat = $format
            ?? (defined('PN_DATE_FORMAT') ? PN_DATE_FORMAT : DATE_ATOM);

        return htmlspecialchars(date($resolvedFormat, $this->timestamp));
    }

    public function __toString(): string
    {
        return $this->format();
    }

    private function normalizeTimestamp(DateTimeInterface|int|string $timestamp): int
    {
        if ($timestamp instanceof DateTimeInterface) {
            return $timestamp->getTimestamp();
        }

        if (is_int($timestamp)) {
            return $timestamp;
        }

        if (is_numeric($timestamp)) {
            return (int) $timestamp;
        }

        $parsed = strtotime((string) $timestamp);
        return $parsed !== false ? $parsed : time();
    }
}
