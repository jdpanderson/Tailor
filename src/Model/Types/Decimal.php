<?php

namespace Tailor\Model\Types;

/**
 * A class representing fixed precision numeric types.
 */
class Decimal extends BaseType
{
    /**
     * Property used by BaseType to determine relevant fields.
     *
     * @var string[]
     */
    protected static $fields_ = ['precision', 'scale'];

    /**
     * Total digits stored, before and after decimal point.
     *
     * @var int
     */
    public $precision = 10;

    /**
     * Digits stored to the right of the decimal point.
     *
     * @var int
     */
    public $scale = 2;
}
