<?php

namespace Phoboslab\Pagenode;

/**
 * DateTime class - a simple wrapper for timestamps
 */
class DateTime
{
    protected $timestamp;

    public function __construct($timestamp)
    {
        $this->timestamp = $timestamp;
    }

    public function format($format = PN_DATE_FORMAT)
    {
        return htmlSpecialChars(date($format, $this->timestamp));
    }

    public function __toString()
    {
        return $this->format();
    }
}
