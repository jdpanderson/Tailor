<?php

namespace Tailor\Driver\PDO;

use PDO;
use PDOException;
use Tailor\Driver\DriverException;
use Tailor\Util\String as StringUtil;
use Tailor\Model\Table;
use Tailor\Model\Column;
use Tailor\Model\Types\Boolean;
use Tailor\Model\Types\DateTime;
use Tailor\Model\Types\Decimal;
use Tailor\Model\Types\Enum;
use Tailor\Model\Types\Float;
use Tailor\Model\Types\Integer;
use Tailor\Model\Types\String;

/**
 * Driver providing the SQLite dialect of SQL.
 *
 * Notes:
 * - SQLite's type system is dynamic, not static like other database engines.
 * - SQLite values have storage classes: NULL, INT, REAL, TEXT, and BLOB.
 * - SQLite columns have affinity: TEXT, NUMERIC, INT, REAL, and NONE.
 * - Any value may take any storage class, with the exception of INT PKs.
 * - SQLite will accept nearly any type, even nonsense.
 *
 * Basically, that means that conversion between SQLite any anything else will be lossy.
 *
 */
class SQLite extends PDODriver
{
    /**
     * Note: SQLite's type system is simple.
     */
    private static $intTypeMap = [
        'INT2' => 'SMALLINT',
        'INT4' => 'INTEGER',
        'INT8' => 'BIGINT',
        'TINY INT' => 'TINYINT',
        'SMALL INT' => 'SMALLINT',
        'MEDIUM INT' => 'MEDIUMINT',
        'BIG INT' => 'BIGINT',
        'INT' => 'INTEGER',
    ];

    private static $charTypeMap = [
        'NCHAR' => 'CHAR',
        'NATIVE CHARACTER' => 'CHAR',
        'NVARCHAR' => 'VARCHAR',
        'VARYING CHARACTER' => 'VARCHAR',
        'CHARACTER' => 'CHAR',
    ];

    private static $floatTypeMap = [
        'DOUBLE PRECISION' => 'DOUBLE',
        'REAL' => 'DOUBLE',
    ];

    /**
     * Map integer types to their sizes in bytes.
     *
     * @var int[]
     */
    private static $intSizeMap = [
        'TINYINT' => 1,
        'SMALLINT' => 2,
        'MEDIUMINT' => 3,
        'INTEGER' => 4,
        'BIGINT' => 8
    ];

    /**
     * Maximum lengths of string types
     *
     * @var int[]
     */
    private static $strLengthMap = [
        'TINYBLOB' => 255, // pow(2, 8) - 1
        'TINYTEXT' => 255,
        'BLOB' => 65535, // pow(2, 16) - 1
        'TEXT' => 65535,
        'MEDIUMBLOB' => 16777215, // pow(2, 24) - 1
        'MEDIUMTEXT' => 16777215,
        'LONGBLOB' => 4294967295, // pow(2, 32) - 1
        'LONGTEXT' => 4294967296
    ];

    /**
     * Map floating point types to their stored sizes in bytes.
     *
     * @var int[]
     */
    private static $floatSizeMap = [
        'FLOAT' => 4,
        'DOUBLE' => 8
    ];

    /**
     * Quote an identifier, like a table or column name.
     *
     * @param string $identifier The identifier to be quoted.
     * @return string The quoted identifier.
     */
    private static function quoteIdentifier($identifier)
    {
        /* MySQL treats `` as an escaped ` */
        return '`' . str_replace('`', '``', $identifier) . '`';
    }

    /**
     * Get a list of tables that reside within a schema in a database.
     *
     * @param string $database The name of the database. Ignored by SQLite.
     * @param string $schema The name of the schema. Ignored by SQLite.
     * @return string[] A list of table names.
     */
    public function getTableNames($database, $schema)
    {
        /* Return all table names, excluding the sqlite namespaced tables. */
        return $this->query("SELECT name FROM sqlite_master WHERE type=?", ['table'], PDO::FETCH_COLUMN);
    }

    /**
     * Fetch the structure of a table for a given database, schema, and table.
     *
     * @param string $database The name of the database.
     * @param string $schema The name of the schema.
     * @param string $tbl THe name of the table.
     * @return Table A Table object that represents the table structure in a database.
     */
    public function getTable($database, $schema, $tbl)
    {
        /* This pragma returns one row for each column in the named table. Columns in the result set include the column
         * name, data type, whether or not the column can be NULL, and the default value for the column. The "pk"
         * column in the result set is zero for columns that are not part of the primary key, and is the index of the
         * column in the primary key for columns that are part of the primary key. */
        $columns = $this->query(sprintf("PRAGMA table_info(%s)", $this->quoteIdentifier($tbl)), [], PDO::FETCH_ASSOC);

        //var_dump($columns);

        /*
        Column example:
        'cid' =>
        string(1) "0"
        'name' =>
        string(2) "tt"
        'type' =>
        string(8) "tinytext"
        'notnull' =>
        string(1) "0"
        'dflt_value' =>
        NULL
        'pk' =>
        string(1) "0"
        */

        if (!$columns) {
            return false;
        }

        $table = new Table();
        $table->name = $tbl;
        foreach ($columns as $colData) {
            $column = new Column($colData['name']);
            $column->null = !(int)$colData['notnull'];
            $column->primary = ((int)$colData['pk']) > 0;
            $column->default = $colData['dflt_value'];
            //$column->sequence = // XXX TODO May need to parse sqlite_master for table info.

            list($type, $typeParams, $typeExtra) = $this->parseTypeParams($colData['type']);

            switch ($type) {
                case 'CHAR':
                case 'VARCHAR':
                case 'BINARY':
                case 'VARBINARY':
                    $column->type = new String(
                        (int)$typeParams,
                        !in_array($type, ['CHAR', 'BINARY']),
                        strpos($type, 'BINARY') !== false
                    );
                    break;

                case 'TINYTEXT':
                case 'TEXT':
                case 'MEDIUMTEXT':
                case 'LONGTEXT':
                case 'TINYBLOB':
                case 'BLOB':
                case 'MEDIUMTBLOB':
                case 'LONGBLOB':
                    $column->type = new String(self::$strLengthMap[$type], true, strpos($type, 'BLOB') !== false);
                    break;

                case 'TINYINT':
                case 'SMALLINT':
                case 'MEDIUMINT':
                case 'INT':
                case 'INTEGER':
                case 'BIGINT':
                    $column->type = new Integer(self::$intSizeMap[$type], $typeExtra === 'UNSIGNED');
                    break;

                case 'FLOAT':
                case 'REAL':
                case 'DOUBLE':
                case 'DOUBLE PRECISION':
                    $column->type = new Float(self::$floatSizeMap[$type]);
                    break;

                case 'DECIMAL':
                case 'NUMERIC':
                    list($precision, $scale) = explode(',', $typeParams);
                    $column->type = new Decimal($precision, $scale);
                    break;

                case 'DATE':
                case 'TIME':
                case 'DATETIME':
                case 'TIMESTAMP':
                    $column->type = new DateTime(
                        $type !== 'TIME',
                        $type !== 'DATE',
                        $type === 'TIMESTAMP' // Timestamps are stored in UTC
                    );
                    break;

                default:
                    throw new DriverException("Type $type is not known");
            }

            $table->columns[] = $column;
        }
        return $table;
    }

    /**
     * Create or update a table in a database.
     *
     * @param string $database The name of the database.
     * @param string $schema The name of the schema.
     * @param Table $table The Table object representing what the table should become.
     * @param bool $force If false, the driver should not perform possibly destructive changes.
     * @return bool True if operations were performed successfully.
     */
    public function setTable($database, $schema, Table $table, $force = false)
    {
        /* We either create or alter depending on whether the table exists. */
        $curTable = $this->getTable($database, $schema, $table->name);

        if ($curTable === false) {
            $cols = [];
            foreach ($table->columns as $column) {
                $cols[] = $this->columnToSQL($column);
            }

            $sql = sprintf(
                "CREATE TABLE %s (\n%s\n)",
                $this->quoteIdentifier($table->name),
                implode(",\n", $cols)
            );
        } else {
            $sql = [];
            list($add, $drop, $change) = Table::columnDiff($curTable, $table);

            foreach ($drop as $dropCol) {
                $sql[] = sprintf("DROP COLUMN %s", $this->quoteIdentifier($dropCol->name));
            }

            foreach ($add as $addCol) {
                $sql[] = sprintf("ADD COLUMN %s", $this->columnToSQL($addCol));
            }

            foreach ($change as $changedCols) {
                list($oldCol, $newCol) = $changedCols;
                $sql[] = sprintf("MODIFY %s", $this->columnToSQL($newCol));
            }

            if (empty($sql)) {
                return true;
            }

            $sql = sprintf(
                "ALTER TABLE %s %s",
                $tableSQL,
                implode(",", $sql)
            );
        }

        return $this->exec($sql) !== false;
    }

    /**
     * Drop/delete a table.
     *
     * @param string $database The name of the database.
     * @param string $schema The name of the schema.
     * @param string $table The name of the table to be dropped.
     * @return bool True if the operation was performed successfully.
     */
    public function dropTable($database, $schema, $table)
    {
        return $this->exec(sprintf("DROP TABLE %s", $this->quoteIdentifier($table))) !== false;
    }

    /**
     * Parse SQLite type details, e.g. The 50 in VARCHAR(50)
     *
     * @param string $type The type as returned by MySQL
     * @return [string, string, string] The bare type, its params, and anything extra.
     */
    private static function parseTypeParams($type)
    {
        $extra = '';

        /* SQLite puts UNSIGNED first, which muddles the type. Parse it first. */
        if (stripos($type, 'UNSIGNED ') === 0) {
            $extra = 'UNSIGNED';
            $type = substr($type, strlen('UNSIGNED ')); // Strip off 'UNSIGNED '
        }

        if (strpos($type, '(') === false) {
            return [strtoupper($type), null, null];
        }

        list($type, $params) = explode('(', $type, 2);

        if (($bracket = strrpos($params, ')')) !== false) {
            $params = substr($params, 0, $bracket);
        }

        return [strtoupper($type), rtrim($params, ')'), $extra];
    }

    /**
     * Turn a column model into SQL for alter or create.
     *
     * @param Column $column The column to convert.
     * @return string A partial chunk of SQL.
     */
    private function columnToSQL($column)
    {
        $sql = [self::quoteIdentifier($column->name)];

        if ($column->type instanceof String) {
            $type = $column->type->binary ? "BLOB" : "VARCHAR({$column->type->length})";
        } elseif ($column->type instanceof Integer || $column->type instanceof Boolean) {
            $type = "INTEGER";
        } elseif ($column->type instanceof Float) {
            $type = "FLOAT";
        } elseif ($column->type instanceof Decimal) {
            $type = "DECIMAL({$column->type->precision}, {$column->type->scale})";
        } elseif ($column->type instanceof DateTime) {
            $type = $column->type->time ? "DATETIME" : "DATE";
        } elseif ($column->type instanceof Enum) {
            $len = array_reduce($column->type->values, function($carry, $item) { return max($carry, $item); }, 0);
            $type = sprintf("VARCHAR(%d)", $len);
        } else {
            throw new DriverException("Unsupported type: " . get_class($column->type));
        }

        $sql[] = $type;

        if ($column->primary) {
            $sql[] = "PRIMARY KEY";
        }
        if ($column->sequence) {
            $sql[] = "AUTOINCREMENT";
        }
        if (!$column->null) {
            $sql[] = "NOT NULL";
        }
        if ($column->default !== null) {
            $default = $this->pdo->quote($column->default);
            $sql[] = "DEFAULT {$default}";
        }

        return implode(" ", $sql);
    }
}
