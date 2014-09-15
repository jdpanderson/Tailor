<?php

namespace Tailor\Model\Types;

/**
 * A class representing an enumeration.
 */
class Enum extends BaseType
{
    /**
     * Property used by BaseType to determine relevant fields.
     *
     * @var string[]
     */
    protected static $fields_ = ['values'];

    /**
     * Create an enum type instance.
     *
     * @param string[] $values The list of values accepted by this enum.
     */
    public function __construct($values = null)
    {
        if (is_array($values)) {
            $this->values = $values;
        } elseif (func_num_args() > 0) {
            $this->values = func_get_args();
        }
    }

    /**
     * The list of values accepted by this enum
     *
     * @var string[]
     */
    public $values;
}
