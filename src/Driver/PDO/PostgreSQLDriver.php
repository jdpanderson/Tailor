<?php

namespace Tailor\Driver;

use Tailor\Model\Table;

/**
 * Interface expected to be implemented by all drivers.
 */
class PostgreSQLDriver extends PDODriver
{
    /**
     * Get the PDO DSN prefix; Always 'mysql:'.
     *
     * @return string The DSN prefix for this driver.
     */
    public static function getDSNPrefix()
    {
        return 'pgsql:';
    }

    /**
     * Get a list of available database names.
     *
     * @return string[] A list of database names.
     */
    public function getDatabaseNames()
    {
        return false;
    }

    /**
     * Get a list of available schemas in a database.
     *
     * @param string $database The database name from which schema names should be retrieved.
     * @return string[] A list of schema names.
     */
    public function getSchemaNames($database)
    {
        return false;
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
        return false;
    }

    /**
     * Fetch the structure of a table for a given database, schema, and table.
     *
     * @param string $database The name of the database.
     * @param string $schema The name of the schema.
     * @param string $table THe name of the table.
     * @return Table A Table object that represents the table structure in a database.
     */
    public function getTable($database, $schema, $table)
    {
        return false;
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
        return false;
    }

    /**
     * Create a database with a given name.
     *
     * @param string $database The name for the new database.
     * @return bool True if the operation was performed successfully.
     */
    public function createDatabase($database)
    {
        return false;
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
        return false;
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
        return false;
    }
}
