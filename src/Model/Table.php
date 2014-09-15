<?php

namespace Tailor\Model;

/**
 * Class that models a table in a relational database.
 */
class Table
{
    /**
     * The name of the table.
     *
     * @var string
     */
    public $name;

    /**
     * The columns that make up the table.
     *
     * @var Column[]
     */
    public $columns = [];

    /**
     * Create a new table model.
     *
     * @param string $name THe name of the table to create.
     * @param Column[] $columns The columns that belong to the table.
     */
    public function __construct($name = null, $columns = null)
    {
        if (!empty($name)) {
            $this->name = $name;
        }

        if (is_array($columns)) {
            foreach ($columns as $column) {
                if ($column instanceof Column) {
                    $this->columns[] = $column;
                }
            }
        }
    }

    /**
     * Compare this table to another.
     *
     * @param Table $table The table to which this table will be compared.
     */
    public function equals(Table $table)
    {
        if ($this->name !== $table->name) {
            return false;
        }

        foreach ($this->columns as $idx => $column) {
            if (!isset($column, $table->columns[$idx]) || !$column->equals($table->columns[$idx])) {
                return false;
            }
        }

        return true;
    }

    public function __clone()
    {
        foreach ($this->columns as $idx => &$column) {
            if (isset($column)) {
                $column = clone $column;
            }
        }
    }
}
