<?php

namespace Pagenode;

use Stringable;

/**
 * DateTime class - a simple wrapper for timestamps
 */
class DateTime implements Stringable
{
    public function __construct(protected $timestamp)
    {
    }

    public function format($format = PN_DATE_FORMAT): string
    {
        return htmlspecialchars(date($format, $this->timestamp));
    }

    public function __toString(): string
    {
        return $this->format();
    }
}
