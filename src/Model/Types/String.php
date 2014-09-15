<?php

namespace Tailor\Model\Types;

/**
 * A class representing character string types.
 */
class String extends BaseType
{
    /**
     * Property used by BaseType to determine relevant fields.
     *
     * @var string[]
     */
    protected static $fields_ = ['length', 'variable', 'binary'];

    /**
     * Create a new string model type.
     *
     * @param int $length The length of the new string.
     * @param bool $variable True if the string should be variable length.
     * @param bool $binary True if the string should represent binary data.
     */
    public function __construct($length = null, $variable = null, $binary = false)
    {
        if (isset($length) && is_int($length) && $length > 0) {
            $this->length = $length;
        }

        if (isset($variable)) {
            $this->variable = (bool)$variable;
        }

        if (isset($binary)) {
            $this->binary = (bool)$binary;
        }
    }

    /**
     * The maximum length
     *
     * @var int
     */
    public $length = 255;

    /**
     * Whether the length is variable.
     *
     * @var bool
     */
    public $variable = true;

    /**
     * Whether the column is treated as binary.
     *
     * @var bool
     */
    public $binary = false;
}
