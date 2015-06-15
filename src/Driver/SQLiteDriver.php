<?php

namespace Tailor\Driver;

use PDO;
use PDOException;
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
 * Driver providing the MySQL dialect of SQL.
 */
class SQLiteDriver extends BaseDriver
{
    /**
     * Type identifier for this driver.
     */
    const DRIVER_TYPE = 'sqlite';

    /**
     * Constants for options accepted by this driver.
     */
    const OPT_DSN = 'dsn';

    /**
     * The PDO object used by this driver.
     *
     * @var PDO
     */
    private $pdo;

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
        'TINYINY' => 1,
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
     * Get a driver instance, if the type can be handled by this driver.
     *
     * @param string $type A unique identifier for a driver type, like SQLite or JSON.
     * @param mixed[] $opts An associative array of options to pass in for the driver.
     * @return Driver A configured driver instance, or false if the type identifier can't be handled.
     * @throws DriverException Expected to throw an exception if the type can be handled, but something is amiss.
     */
    public static function getDriver($type, $opts)
    {
        /* Allow directly passing in a PDO_MySQL DSN */
        if (substr($type, 0, 7) === 'sqlite:') {
            $opts[self::OPT_DSN] = $type;
            $type = self::DRIVER_TYPE;
        }

        /* Allow passing in a direct filename. */
        foreach ([".sqlite", ".sqlite3", ".sq3"] as $ext) {
            if (substr($type, -1 * strlen($ext)) === $ext) {
                $opts[self::OPT_DSN] = "sqlite:{$type}";
                $type = self::DRIVER_TYPE;
            }
        }

        if ($type !== self::DRIVER_TYPE) {
            return false;
        }

        if (empty($opts[self::OPT_DSN])) {
            throw new DriverException("Unable to connect to database: No DSN available.");
        }

        return new SQLiteDriver(new PDO($opts[self::OPT_DSN], $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]));
    }

    /**
     * Creates a new MySQL driver instance.
     *
     * @param PDO $pdo A configured PDO instance.
     */
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Get a list of available database names.
     *
     * @return string[] A list of database names.
     */
    public function getDatabaseNames()
    {
        return false; /* Not really supported by SQLite */
    }

    /**
     * Get a list of available schemas in a database.
     *
     * @param string $database The database name from which schema names should be retrieved.
     * @return string[] A list of schema names.
     */
    public function getSchemaNames($database)
    {
        return false; /* Not really supported by SQLite */
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
        /* Return all table names, excluding the sqlite namespaced tables. */
        return $this->run("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'", PDO::FETCH_COLUMN);
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
        /*This pragma returns one row for each column in the named table. Columns in the result set include the column name, data type, whether or not the column can be NULL, and the default value for the column. The "pk" column in the result set is zero for columns that are not part of the primary key, and is the index of the column in the primary key for columns that are part of the primary key.*/
        $columns = $this->run(sprintf("PRAGMA table_info(%s)", $this->quoteIdentifier($tbl)), PDO::FETCH_ASSOC);

        var_dump($columns);

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
        foreach ($columns as $columnData) {
            $column = new Column();
            $column->name = $columnData['name'];
            $column->null = !(int)$columnData['notnull'];
            $column->primary = ((int)$columnData['pk']) > 0;
            //$column->unique = ($columnData['key'] === 'UNI');
            $column->default = $columnData['dflt_value'];

            list($type, $typeParams, $typeExtra) = $this->parseTypeParams($columnData['type']);

            //$column->sequence = (strtoupper($columnData['extra']) === 'AUTO_INCREMENT');

            switch ($type) {
                case 'CHAR':
                case 'VARCHAR':
                case 'BINARY':
                case 'VARBINARY':
                    $column->type = new String();
                    $column->type->length = (int)$typeParams;
                    $column->type->variable = !in_array($type, ['CHAR', 'BINARY']);
                    $column->type->binary = strpos($type, 'BINARY') !== false;
                    break;

                case 'TINYTEXT':
                case 'TEXT':
                case 'MEDIUMTEXT':
                case 'LONGTEXT':
                case 'TINYBLOB':
                case 'BLOB':
                case 'MEDIUMTBLOB':
                case 'LONGBLOB':
                    $column->type = new String();
                    $column->type->length = self::$strLengthMap[$type];
                    $column->type->variable = true;
                    $column->type->binary = strpos($type, 'BLOB') !== false;
                    break;

                case 'TINYINT':
                case 'SMALLINT':
                case 'MEDIUMINT':
                case 'INT':
                case 'INTEGER':
                case 'BIGINT':
                    $column->type = new Integer();
                    $column->type->size = self::$intSizeMap[$type];
                    $column->type->unsigned = ($typeExtra === 'UNSIGNED');
                    break;

                case 'FLOAT':
                case 'REAL':
                case 'DOUBLE':
                case 'DOUBLE PRECISION':
                    $column->type = new Float();
                    $column->type->size = self::$floatSizeMap[$type];
                    break;

                case 'DECIMAL':
                case 'NUMERIC':
                    list($precision, $scale) = explode(',', $typeParams);
                    $column->type = new Decimal();
                    $column->type->precision = $precision;
                    $column->type->scale = $scale;
                    break;

                case 'DATE':
                case 'TIME':
                case 'DATETIME':
                case 'TIMESTAMP':
                    $column->type = new DateTime();
                    $column->type->date = ($type !== 'TIME');
                    $column->type->time = ($type !== 'DATE');
                    $column->type->zone = $type === 'TIMESTAMP'; // Timestamps are stored in UTC
                    break;


                case 'BIT': /* Easily supportable, but not of major interest to me right now. */
                case 'SET': /* Not widely supported. Can be represented by a 64-bit integer. */
                case 'YEAR': /* Not widely supported. Can be represented by a >16-bit integer. */
                    throw new DriverException("Sorry, $type is not yet supported");

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
            return [strtoupper($type), null];
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