<?php

namespace Tailor\Driver\MySQL;

use PDO;
use PDOException;
use Tailor\Driver\BaseDriver;
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
 * Interface expected to be implemented by all drivers.
 */
class Driver extends BaseDriver
{
    /**
     * Constants for options accepted by this driver.
     */
    const OPT_PDO = 'pdo';
    const OPT_DSN = 'dsn';
    const OPT_USERNAME = 'username';
    const OPT_PASSWORD = 'password';

    /**
     * The PDO object used by this driver.
     *
     * @var PDO
     */
    private $pdo;

    /**
     * Map integer types to their sizes in bytes.
     *
     * @var int[]
     */
    private static $intSizeMap = [
        'TINYINY' => 1,
        'SMALLINT' => 2,
        'MEDIUMINT' => 3,
        'INT' => 4,
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
        'DOUBLE' => 8,
        'DOUBLE PRECISION' => 8,
        'REAL' => 8
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
     * Creates a new MySQL driver instance.
     *
     * @param PDO $pdo A configured PDO instance.
     */
    public function __construct(array $opts)
    {
        if (isset($opts[self::OPT_PDO]) && $opts[self::OPT_PDO] instanceof PDO) {
            $this->pdo = $opts[self::OPT_PDO];
        } elseif (!empty($opts[self::OPT_DSN])) {
            $user = empty($opts[self::OPT_USERNAME]) ? null : $opts[self::OPT_USERNAME];
            $pass = empty($opts[self::OPT_PASSWORD]) ? null : $opts[self::OPT_PASSWORD];
            $this->pdo = new PDO($opts[self::OPT_DSN], $user, $pass);
        } else {
            throw new DriverException("Unable to connect to database: No DSN available.");
        }
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    /**
     * Get a list of available database names.
     *
     * @return string[] A list of database names.
     */
    public function getDatabaseNames()
    {
        /* Same as SHOW SCHEMAS in MySQL */
        return $this->run("SHOW DATABASES", PDO::FETCH_COLUMN);
    }

    /**
     * Get a list of available schemas in a database.
     *
     * @param string $database The database name from which schema names should be retrieved.
     * @return string[] A list of schema names.
     */
    public function getSchemaNames($database)
    {
        /* Same as SHOW DATABASES in MySQL */
        //return $this->run("SHOW SCHEMAS", PDO::FETCH_COLUMN);

        /* Until MySQL supports schemas, return the "default" schema if there's no error. */
        return $this->run("SHOW SCHEMAS", PDO::FETCH_COLUMN) ? [Driver::SCHEMA_DEFAULT] : false;
    }

    /**
     * Get a list of tables that reside within a schema in a database.
     *
     * @param string $database The name of the database.
     * @param string $schema The name of the schema.
     * @return string[] A list of table names.
     */
    public function getTableNames($database, $schema)
    {
        if ($database = $this->interpretDatabase($database, $schema)) {
            return $this->run(sprintf("SHOW TABLES IN %s", $this->quoteIdentifier($database)), PDO::FETCH_COLUMN);
        } else {
            return $this->run("SHOW TABLES", PDO::FETCH_COLUMN);
        }
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
        if ($database = $this->interpretDatabase($database, $schema)) {
            $columns = $this->run(sprintf(
                "DESCRIBE %s.%s",
                $this->quoteIdentifier($database),
                $this->quoteIdentifier($tbl)
            ), PDO::FETCH_ASSOC);
        } else {
            $columns = $this->run(sprintf("DESCRIBE %s", $this->quoteIdentifier($tbl)), PDO::FETCH_ASSOC);
        }

        if (!$columns) {
            return false;
        }

        $table = new Table();
        $table->name = $tbl;
        foreach ($columns as $columnData) {
            $table->columns[] = $this->getColumnFromDescription($columnData);
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

        if ($database = $this->interpretDatabase($database, $schema)) {
            $tableSQL = sprintf(
                "%s.%s",
                $this->quoteIdentifier($database),
                $this->quoteIdentifier($table->name)
            );
        } else {
            $tableSQL = $this->quoteIdentifier($table->name);
        }

        if ($curTable === false) {
            $cols = [];
            foreach ($table->columns as $column) {
                $cols[] = $this->columnToSQL($column);
            }

            $sql = sprintf(
                "CREATE TABLE %s (\n%s\n) ENGINE=InnoDB DEFAULT CHARSET=utf8",
                $tableSQL,
                implode(",\n", $cols)
            );
        } else {
            /* Index of columns in the table's new state */
            $newCols = [];
            foreach ($table->columns as $idx => $column) {
                $newCols[$idx] = $column->name;
            }

            /* Index of columns in the table's old state */
            $oldCols = [];
            foreach ($curTable->columns as $idx => $column) {
                $oldCols[$idx] = $column->name;
            }

            $sql = [];

            /* Drop any tables that are in the old and not the new */
            foreach (array_diff($oldCols, $newCols) as $remove) {
                $sql[] = sprintf("DROP COLUMN %s", $this->quoteIdentifier($remove));
            }

            /* Add columns that are in the new but not the old */
            foreach (array_diff($newCols, $oldCols) as $create) {
                foreach ($table->columns as $column) {
                    if ($column->name === $create) {
                        $sql[] = sprintf("ADD COLUMN %s", $this->columnToSQL($column));
                        break;
                    }
                }
            }

            /* Compare any common columns, and modify if unequal */
            foreach (array_intersect($newCols, $oldCols) as $common) {
                $newCol = $oldCol = null;
                foreach ($table->columns as $column) {
                    if ($column->name === $common) {
                        $newCol = $column;
                        break;
                    }
                }
                foreach ($curTable->columns as $column) {
                    if ($column->name === $common) {
                        $oldCol = $column;
                        break;
                    }
                }

                if ($newCol && $oldCol && !$newCol->equals($oldCol)) {
                    $sql[] = sprintf("MODIFY %s", $this->columnToSQL($newCol));
                }
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

        return $this->run($sql, false) !== false;
    }

    /**
     * Create a database with a given name.
     *
     * @param string $database The name for the new database.
     * @return bool True if the operation was performed successfully.
     */
    public function createDatabase($database)
    {
        if ($database === Driver::DATABASE_DEFAULT) {
            return false;
        }

        return $this->run(sprintf("CREATE DATABASE %s", $this->quoteIdentifier($database)), false) !== false;
    }

    /**
     * Create a schema with a given name; MySQL doesn't really support this.
     *
     * @param string $database The name of the database in which the schema should be created.
     * @param string $schema The name for the new schema.
     * @return bool True if the operation was performed successfully.
     */
    public function createSchema($database, $schema)
    {
        if ($database = $this->interpretDatabase($database, $schema)) {
            return $this->run(sprintf("CREATE SCHEMA %s", $this->quoteIdentifier($database)), false) !== false;
        }
        return false;
    }

    /**
     * Drop/delete a database.
     *
     * @param string $database The name of the database to be dropped.
     * @return bool True if the operation was performed successfully.
     */
    public function dropDatabase($database)
    {
        if ($database === Driver::DATABASE_DEFAULT) {
            return false;
        }

        return $this->run(sprintf("DROP DATABASE %s", $this->quoteIdentifier($database)), false) !== false;
    }

    /**
     * Drop/delete a schema.
     *
     * @param string $database The name of the database in which the schema resides.
     * @param string $schema The name of the schema to be dropped.
     * @return bool True if the operation was performed successfully.
     */
    public function dropSchema($database, $schema)
    {
        /* MySQL doesn't really support schemata; Don't interpret DB here to prevent a possible unexpected drop. */
        //$schema = $this->quoteIdentifier($schema);
        //return $this->run("DROP SCHEMA {$schema}", false) !== false;
        return false;
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
        if ($database = $this->interpretDatabase($database, $schema)) {
            $tableSQL = sprintf(
                "%s.%s",
                $this->quoteIdentifier($database),
                $this->quoteIdentifier($table)
            );
        } else {
            $tableSQL = $this->quoteIdentifier($table);
        }

        return $this->run(sprintf("DROP TABLE %s", $tableSQL), false) !== false;
    }

    /**
     * Get a column model from a DESCRIBE TABLE ? row.
     *
     * @param string[] $desc An associative array representing a row from describe command output.
     * @return Column A column model based on the description row.
     */
    private function getColumnFromDescription($desc)
    {
        $desc = array_change_key_case($desc, CASE_LOWER);
        $column = new Column($desc['field'], null);
        $column->null = ($desc['null'] !== 'NO');
        $column->primary = ($desc['key'] === 'PRI');
        $column->unique = ($desc['key'] === 'UNI');
        $column->default = ($desc['default'] === 'NULL') ? null : $desc['default'];
        list($type, $typeParams, $typeExtra) = $this->parseTypeParams($desc['type']);
        $column->sequence = (strtoupper($desc['extra']) === 'AUTO_INCREMENT');
        switch ($type) {
            case 'CHAR':
            case 'VARCHAR':
            case 'BINARY':
            case 'VARBINARY':
                $column->type = new String((int)$typeParams, !in_array($type, ['CHAR', 'BINARY']), strpos($type, 'BINARY') !== false);
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

            case 'ENUM':
                $column->type = new Enum(StringUtil::parseQuotedList($typeParams));
                break;
            case 'BIT': /* Easily supportable, but not of major interest to me right now. */
            case 'SET': /* Not widely supported. Can be represented by a 64-bit integer. */
            case 'YEAR': /* Not widely supported. Can be represented by a >16-bit integer. */
                throw new DriverException("Sorry, $type is not yet supported");

            default:
                throw new DriverException("Type $type is not known");
        }

        return $column;
    }

    /**
     * Interpret the given database and schema for use internally.
     *
     * If the database is given as non-default use that, otherwise try the schema. Falls back to nothing.
     *
     * @param string $database A database name.
     * @param string $schema A schema name.
     * @return string A database or schema name. False if neither is available.
     */
    private static function interpretDatabase($database, $schema)
    {
        if ($database !== Driver::DATABASE_DEFAULT) {
            return $database;
        } elseif ($schema !== Driver::SCHEMA_DEFAULT) {
            return $schema;
        }
        return false;
    }

    /**
     * Parse MySQL type parameters, e.g. The 50 in VARCHAR(50)
     *
     * @param string $type The type as returned by MySQL
     * @return [string, string, string] The bare type, its params, and anything extra.
     */
    private static function parseTypeParams($type)
    {
        if (strpos($type, '(') === false) {
            return [strtoupper($type), null, null];
        }

        list($type, $params) = explode('(', $type, 2);

        $extra = '';
        if (($bracket = strrpos($params, ')')) !== false) {
            $extra = strtoupper(trim(substr($params, $bracket + 1)));
        }

        return [strtoupper($type), rtrim($params, ')'), $extra];
    }

    /**
     * Run a mysql control query and return the result in the given mode.
     *
     * @param string $query The query to execute
     * @param int $fetchMode The PDO::FETCH_* mode, or false to execute rather than query.
     * @return mixed The result, or false on failure.
     */
    private function run($query, $fetchMode)
    {
        try {
            if ($fetchMode === false) {
                return $this->pdo->exec($query);
            }

            if (!($stmt = $this->pdo->query($query))) {
                return false;
            }
            return $stmt->fetchAll($fetchMode);
        } catch (PDOException $e) {
            return false;
        }
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
            if ($column->type->length < 255) {
                $type = $column->type->variable ? 'VAR' : '';
                $type .= $column->type->binary ? 'BINARY' : 'CHAR';
                $type .= "({$column->type->length})";
            } elseif ($column->type->length <= 255) {
                $type = $column->type->binary ? 'TINYBLOB' : 'TINYTEXT';
            } elseif ($column->type->length <= 65535) {
                $type = $column->type->binary ? 'BLOB' : 'TEXT';
            } elseif ($column->type->length <= (pow(2, 24) - 1)) {
                $type = $column->type->binary ? 'MEDIUMBLOB' : 'MEDIUMTEXT';
            } else {
                $type = $column->type->binary ? 'LONGBLOB' : 'LONGTEXT';
            }
        } elseif ($column->type instanceof Integer) {
            $typeMap = array_flip(self::$intSizeMap);

            if (isset($typeMap[$column->type->size])) {
                $type = $typeMap[$column->type->size];
            } else {
                $type = ($column->type->size > 4) ? 'BIGINT' : 'INT';
            }
        } elseif ($column->type instanceof Float) {
            $type = ($column->type->size > 4) ? 'DOUBLE' : 'FLOAT';
        } elseif ($column->type instanceof Decimal) {
            $type = "DECIMAL({$column->type->precision}, {$column->type->scale})";
        } elseif ($column->type instanceof DateTime) {
            if ($column->type->date && $column->type->time) {
                $type = $column->type->zone ? "TIMESTAMP" : "DATETIME";
            } elseif ($column->type->date) {
                $type = "DATE";
            } elseif ($column->type->time) {
                $type = "TIME";
            }
        } elseif ($column->type instanceof Boolean) {
            /* MySQL doesn't directly support boolean */
            $type = "TINYINT(1) UNSIGNED";
        } elseif ($column->type instanceof Enum) {
            $values = array_map([$this->pdo, 'quote'], $column->type->values);
            $type = "ENUM(" . implode(",", $values) . ")";
        } else {
            throw new DriverException("Unsupported type: " . get_class($column->type));
        }

        $sql[] = $type;

        if ($column->primary) {
            $sql[] = "PRIMARY KEY";
        }
        if ($column->sequence) {
            $sql[] = "AUTO_INCREMENT";
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
