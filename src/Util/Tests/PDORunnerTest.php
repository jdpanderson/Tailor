<?php

namespace Tailor\Util\Tests;

use PDO;
use Tailor\Util\PDORunner;

class PDORunnerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @test
     */
    public function synopsis()
    {
        $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $runner = new PDORunner($pdo);

        /* Executes statements without params, returning affected rows */
        $this->assertEquals(
            0,
            $runner->exec("CREATE TABLE test (foo int, bar varchar)"),
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
            $runner->exec("INSERT INTO test VALUES(?, ?)", [1, "two"]),
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
            $runner->query("SELECT bar FROM test", [], PDO::FETCH_COLUMN),
            "Expected the contents of the 'bar' column ('two')"
        );

        /* Executes queries with params in the given fetch mode, returning data. */
        $this->assertEquals(
            [(object)['foo' => 1]],
            $runner->query("SELECT foo FROM test WHERE bar=:bar", ['bar' => 'two'], PDO::FETCH_OBJ),
            "Expected the contents of the 'foo' column (1) within an object"
        );
    }

    public function testGetter()
    {
        $pdo = new PDO('sqlite::memory:');
        $runner = new PDORunner($pdo);
        $this->assertTrue($runner->getPDO() === $pdo);
    }

    public function testQuote()
    {
        $runner = new PDORunner(new PDO('sqlite::memory:'));
        $this->assertEquals('\'foo\'\'bar\'', $runner->quote('foo\'bar'));
    }
}
