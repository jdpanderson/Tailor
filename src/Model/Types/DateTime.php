<?php

namespace Tailor\Model\Types;

use \InvalidArgumentException;

/**
 * A class representing date and/or time types.
 */
class DateTime extends BaseType
{
    /**
     * Property used by BaseType to determine relevant fields.
     *
     * @var string[]
     */
    protected static $fields_ = ['date', 'time', 'zone'];

    /**
     * Create a date and/or time type model.
     *
     * @param bool $date True if this type should store a date.
     * @param bool $time True if this type should store a time.
     * @param bool $zone True if this type should store a time zone.
     */
    public function __construct($date = null, $time = null, $zone = null)
    {
        if (isset($date)) {
            $this->date = (bool)$date;
        }

        if (isset($time)) {
            $this->time = (bool)$time;
        }

        if (isset($zone)) {
            $this->zone = $zone;
        }

        if ($this->date === false && $this->time === false) {
            throw new InvalidArgumentException("A DateTime class must have at least a date or time.");
        }
    }

    /**
     * Flag indicating if this type includes a date.
     *
     * @var bool
     */
    public $date = true;

    /**
     * Flag indicating if this type includes a time.
     *
     * @var bool
     */
    public $time = true;

    /**
     * Flag indicating if this type includes a time zone.
     *
     * @var bool
     */
    public $zone = true;
}
