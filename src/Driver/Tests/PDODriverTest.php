<?php

namespace Tailor\Driver\Tests;

use PDO;
use Tailor\Driver\DriverException;
use Tailor\Driver\PDO\PDODriver;

class PDODriverTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @test
     */
    public function synopsis()
    {
        /* Create a PDO driver from connection params. Functionally identical to below. */
        $driver = new PDODriver([
            PDODriver::OPT_DSN => 'sqlite::memory:',
            PDODriver::OPT_USERNAME => null,
            PDODriver::OPT_PASSWORD => null,
            PDODriver::OPT_OPTIONS => [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
        ]);

        /* Create a PDO driver directly from a PDO object. */
        $pdo = new PDO('sqlite::memory:');
        $driver = new PDODriver([PDODriver::OPT_PDO => $pdo, PDODriver::OPT_OPTIONS => [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]]);

        /* Executes statements without params, returning affected rows */
        $this->assertEquals(
            0,
            $driver->exec("CREATE TABLE test (foo int, bar varchar)"),
            "Expected no rows to be affected"
        );
        $this->assertEquals(
            "test",
            $pdo->query(
                "SELECT tbl_name FROM sqlite_master WHERE type='table' AND tbl_name='test'"
            )->fetch(PDO::FETCH_COLUMN),
            "Expected table was NOT created"
        );

        /* Executes statements with params, returning affected rows */
        $this->assertEquals(
            1,
            $driver->exec("INSERT INTO test VALUES(?, ?)", [1, "two"]),
            "Expected one row to be affected"
        );
        $this->assertEquals(
            [['foo' => 1, 'bar' => 'two']],
            $pdo->query("SELECT * FROM test")->fetchAll(PDO::FETCH_ASSOC),
            "Expected row was NOT inserted"
        );

        /* Executes queries without params in the given fetch mode, returning data. */
        $this->assertEquals(
            ['two'],
            $driver->query("SELECT bar FROM test", [], PDO::FETCH_COLUMN),
            "Expected the contents of the 'bar' column ('two')"
        );

        /* Executes queries with params in the given fetch mode, returning data. */
        $this->assertEquals(
            [(object)['foo' => 1]],
            $driver->query("SELECT foo FROM test WHERE bar=:bar", ['bar' => 'two'], PDO::FETCH_OBJ),
            "Expected the contents of the 'foo' column (1) within an object"
        );
    }

    public function testIncorrectArgs()
    {
        try {
            $drv = new PDODriver([]);
            $this->fail("DriverException expected when insufficient connection arguments provided.");
        } catch (DriverException $e) {
            // Expected
        }
    }

    public function testGetter()
    {
        $pdo = new PDO('sqlite::memory:');
        $driver = new PDODriver([PDODriver::OPT_PDO => $pdo]);
        $this->assertTrue($driver->getPDO() === $pdo);
    }

    public function testQuote()
    {
        $driver = new PDODriver([PDODriver::OPT_PDO => new PDO('sqlite::memory:')]);
        $this->assertEquals('\'foo\'\'bar\'', $driver->quote('foo\'bar'));
    }
}
