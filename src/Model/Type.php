<?php

namespace Tailor\Model;

interface Type
{
    /**
     * Compare one type instance to another.
     *
     * @param Type $type The type to compare.
     * @return bool True if this and the foreign object are equal.
     */
    public function equals(Type $type);
}
