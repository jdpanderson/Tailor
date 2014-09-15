<?php

namespace Tailor\Model\Types;

/**
 * A class representing single and double floating point types.
 */
class Float extends BaseType
{
    /**
     * Property used by BaseType to determine relevant fields.
     *
     * @var string[]
     */
    protected static $fields_ = ['size'];

    /**
     * Create a new integer model
     *
     * @param int $size The size of the integer, generally 1, 2, 4, or 8.
     */
    public function __construct($size = null)
    {
        if (isset($size) && is_int($size) && $size > 0) {
            $this->size = $size;
        }
    }

    /**
     * The float width, in bytes. Typically 4 or 8.
     *
     * @var int
     */
    public $size = 4;
}
