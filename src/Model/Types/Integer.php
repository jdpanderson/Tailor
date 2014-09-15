<?php

namespace Tailor\Model\Types;

/**
 * A class representing integer types.
 */
class Integer extends BaseType
{
    /**
     * Property used by BaseType to determine relevant fields.
     *
     * @var string[]
     */
    protected static $fields_ = ['size', 'unsigned'];

    /**
     * Create an integer type instance.
     *
     * @param int $size The size of the integer in bytes.
     * @param bool $unsigned True if the type should be an unsigned integer.
     */
    public function __construct($size = null, $unsigned = null)
    {
        if (is_int($size)) {
            $this->size = $size;
        }

        if (is_bool($unsigned)) {
            $this->unsigned = $unsigned;
        }
    }

    /**
     * The integer width, in bytes; Usually 1, 2, 4, or 8.
     *
     * @var int
     */
    public $size = 4;

    /**
     * Whether the integer should be unsigned.
     *
     * @var bool
     */
    public $unsigned = false;
}
