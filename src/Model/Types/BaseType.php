<?php

namespace Tailor\Model\Types;

use Tailor\Model\Type;

/**
 * A class representing character string types.
 */
class BaseType implements Type
{
    /**
     * Overridden in base classes to list fields compared to determine equality.
     *
     * @var string[]
     */
    protected static $fields_ = [];

    /**
     * Compare this Type to another
     *
     * @param Type $type The type to compare.
     * @return bool True if this and the foreign object are equal.
     */
    public function equals(Type $type)
    {
        if (get_class($type) != get_class($this)) {
            return false;
        }

        foreach (static::$fields_ as $field) {
            if ($this->$field !== $type->$field) {
                return false;
            }
        }

        return true;
    }
}
