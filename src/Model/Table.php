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

    /**
     * Get a column by name.
     *
     * @param string $name The name of the column to retrieve.
     * @return Column The column, if found. False if not found.
     */
    public function getColumnByName($name)
    {
        foreach ($this->columns as $column) {
            if ($column->name === $name) {
                return $column;
            }
        }
        return false;
    }

    /**
     * Figure out which columns in a table have been added, removed, or changed.
     *
     * @param Table $old The original table
     * @param Table $new The new table
     * @return array A 3-element array (tuple) of added, dropped, and changed columns.
     */
    public static function columnDiff(Table $old, Table $new)
    {
        /* Index of columns in the table's new state */
        $newCols = [];
        foreach ($new->columns as $idx => $column) {
            $newCols[$idx] = $column->name;
        }

        /* Index of columns in the table's old state */
        $oldCols = [];
        foreach ($old->columns as $idx => $column) {
            $oldCols[$idx] = $column->name;
        }

        /* Drop any tables that are in the old and not the new */
        $drop = [];
        foreach (array_diff($oldCols, $newCols) as $dropName) {
            $drop[] = $old->getColumnByName($dropName);
        }

        /* Add columns that are in the new but not the old */
        $add = [];
        foreach (array_diff($newCols, $oldCols) as $addName) {
            $add[] = $new->getColumnByName($addName);
        }

        /* Compare any common columns, and modify if unequal */
        $change = [];
        foreach (array_intersect($newCols, $oldCols) as $commonName) {
            $newCol = $new->getColumnByName($commonName);
            $oldCol = $old->getColumnByName($commonName);

            if ($newCol && $oldCol && !$newCol->equals($oldCol)) {
                $change[] = [$oldCol, $newCol];
            }
        }

        return [$add, $drop, $change];
    }
}
