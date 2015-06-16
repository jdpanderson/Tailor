<?php

namespace Tailor\Model;

/**
 * Class that models a column in a relational database.
 */
class Column
{
    /**
     * The name of the column.
     *
     * @var string
     */
    public $name;

    /**
     * The type used by the column's values.
     *
     * @var Type
     */
    public $type;

    /**
     * Flag indicating if this column is a primary key.
     *
     * @var bool
     */
    public $primary = false;

    /**
     * Flag indicating if this column is associated with a sequence.
     *
     * @var bool
     */
    public $sequence = false;

    /**
     * Flag indicating if this column is nullable.
     *
     * @var bool
     */
    public $null = true;

    /**
     * Flag indicating if this column should have unique values.
     *
     * @var bool
     */
    public $unique = false;

    /**
     * The default value for the column.
     *
     * @var mixed
     */
    public $default;

    /**
     * Create a new column model
     *
     * @param string $name The name of the column.
     * @param Type $type The type for the column.
     */
    public function __construct($name = null, Type $type = null)
    {
        if (!empty($name) && is_string($name)) {
            $this->name = $name;
        }

        if ($type instanceof Type) {
            $this->type = $type;
        }
    }

    public function equals(Column $column)
    {
        foreach (['name', 'primary', 'sequence', 'null', 'unique', 'default'] as $prop) {
            if ($this->$prop !== $column->$prop) {
                return false;
            }
        }

        /* If one, the other, or both aren't set, they're only equal if they're exactly equal */
        if (!isset($this->type, $column->type)) {
            return $this->type === $column->type;
        }

        return $this->type->equals($column->type);
    }

    public function getType()
    {
        return $this->type;
    }

    /**
     * If cloned, make sure the type is also cloned.
     */
    public function __clone()
    {
        if (isset($this->type)) {
            $this->type = clone $this->type;
        }
    }
}
