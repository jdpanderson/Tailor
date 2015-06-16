<?php

namespace Tailor\Driver;

use PDO;
use PDOException;
use Tailor\Model\Table;
use Tailor\Model\Column;
use Tailor\Model\Types\DateTime;
use Tailor\Model\Types\Decimal;
use Tailor\Model\Types\Float;
use Tailor\Model\Types\Integer;
use Tailor\Model\Types\String;

/**
 * Driver that reads and writes to a JSON file.
 */
class JSONDriver extends BaseDriver
{
    /**
     * The option name passed to set the input/output filename.
     */
    const OPT_FILENAME = 'filename';

    /**
     * The file we'll read and write.
     *
     * @var string
     */
    private $filename;

    /**
     * The blob of data that represents database schemata.
     *
     * @var Object
     *
     * Should look something like:
     * {
     *     'database': {
     *         'schema': {
     *             'table': { <table object> }
     *         }
     *     }
     * }
     */
    private $data;

    /**
     * Get an associative array of options supported by the driver.
     *
     * @return string[] An array of driver options, with option as the key pointing to a description in the value.
     */
    public static function getOptions()
    {
        return [
            self::OPT_FILENAME => 'The file name to/from which JSON should be read/written.'
        ];
    }

    /**
     * Create a new JSON Driver
     *
     * @param string $filename The name (and path) to the file.
     */
    public function __construct(array $opts = [])
    {
        parent::__construct();

        if (empty($opts[self::OPT_FILENAME])) {
            throw new DriverException("JSON Driver requires the filename option");
        }

        $this->filename = $opts[self::OPT_FILENAME];
    }

    /**
     * Set the internal data. Public for testing purposes.
     *
     * @private
     */
    public function setData($data)
    {
        $this->data = $data;
    }

    /**
     * Load a JSON file from the filename passed to the constructor.
     *
     * @return Object Usually an object, but returns anything JSON can decode.
     */
    private function load()
    {
        if (isset($this->data)) {
            return $this->data;
        }

        $readable = is_file($this->filename) && is_readable($this->filename);
        if (!$readable || !($json = file_get_contents($this->filename))) {
            return false;
        }

        if (!($json = json_decode($json, true))) {
            return false;
        }

        $this->data = $json;
        return $this->data;
    }

    private function save()
    {
        $json = json_encode($this->data, JSON_PRETTY_PRINT);

        if (!$json) {
            return false;
        }

        $overwritable = is_file($this->filename) && is_writable($this->filename);
        $creatable = is_writable(dirname($this->filename));
        if ($overwritable || $creatable) {
            return file_put_contents($this->filename, $json) !== false;
        }

        return false;
    }

    /**
     * Get a list of available database names.
     *
     * @return string[] A list of database names, or false on failure.
     */
    public function getDatabaseNames()
    {
        if (($data = $this->load()) === false) {
            return false;
        }

        return array_keys($data);
    }

    /**
     * Get a list of available schemas in a database.
     *
     * @param string $database The database name from which schema names should be retrieved.
     * @return string[] A list of schema names, or false on failure.
     */
    public function getSchemaNames($database)
    {
        if (($data = $this->load()) === false) {
            return false;
        }

        if (!isset($data[$database])) {
            return false;
        }

        return array_keys($data[$database]);
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
        if (($data = $this->load()) === false) {
            return false;
        }

        if (!isset($data[$database][$schema])) {
            return false;
        }

        return array_keys($data[$database][$schema]);
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
        if (($data = $this->load()) === false) {
            return false;
        }

        if (!isset($data[$database][$schema][$tbl])) {
            return false;
        }

        /* Unpack the JSON data into its original structure. */
        $tblData = $data[$database][$schema][$tbl];
        $table = new Table();
        foreach ($tblData['columns'] as $colData) {
            $column = new Column();
            $typeCls = "Tailor\\Model\\Types\\" .  $colData['type']['type'];
            $column->type = new $typeCls();
            foreach ($colData['type'] as $field => $value) {
                $column->type->$field = $value;
            }
            unset($colData['type']);
            foreach ($colData as $field => $value) {
                $column->$field = $value;
            }
            $table->columns[] = $column;
        }
        unset($tblData['columns']);
        foreach ($tblData as $field => $value) {
            $table->$field = $value;
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
        if (!isset($this->data)) {
            $this->data = [];
        }

        if (!isset($this->data[$database])) {
            $this->data[$database] = [];
        }

        if (!isset($this->data[$database][$schema])) {
            $this->data[$database][$schema] = [];
        }

        $tblData = (array)$table;
        $tblData['columns'] = [];

        /* Pack the JSON data into a structure we can unserialize */
        foreach ($table->columns as $column) {
            $colData = (array)$column;
            $colData['type'] = (array)$column->type;
            $colData['type']['type'] = $column->getType()->getName();
            $tblData['columns'][] = $colData;
        }

        $this->data[$database][$schema][$table->name] = $tblData;

        $this->save();

        return true;
    }

    /**
     * Create a database with a given name.
     *
     * @param string $database The name for the new database.
     * @return bool True if the operation was performed successfully.
     */
    public function createDatabase($database)
    {
        if (!isset($this->data[$database])) {
            $this->data[$database] = [];
        }
        return true;
    }

    /**
     * Create a schema with a given name.
     *
     * @param string $database The name of the database in which the schema should be created.
     * @param string $schema The name for the new schema.
     * @return bool True if the operation was performed successfully.
     */
    public function createSchema($database, $schema)
    {
        if (!isset($this->data[$database])) {
            return false;
        }

        if (!isset($this->data[$database][$schema])) {
            $this->data[$database][$schema] = [];
        }

        return true;
    }

    /**
     * Drop/delete a database.
     *
     * @param string $database The name of the database to be dropped.
     * @return bool True if the operation was performed successfully.
     */
    public function dropDatabase($database)
    {
        if (isset($this->data[$database])) {
            unset($this->data[$database]);
        }

        return true;
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
        if (isset($this->data[$database][$schema])) {
            unset($this->data[$database][$schema]);
        }

        return true;
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
        if (isset($this->data[$database][$schema][$table])) {
            unset($this->data[$database][$schema][$table]);
        }

        return true;
    }
}
