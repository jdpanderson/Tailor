<?php

namespace Tailor\Driver\Tests;

use PDO;
use Tailor\Model\Table;
use Tailor\Model\Column;
use Tailor\Model\Types\Integer;
use Tailor\Model\Types\String;
use Tailor\Model\Types\Float;
use Tailor\Model\Types\Boolean;
use Tailor\Model\Types\DateTime;
use Tailor\Model\Types\Decimal;
use Tailor\Model\Types\Enum;
use Tailor\Driver\Driver;
use Tailor\Driver\DriverException;
use Tailor\Driver\PDO\SQLite as SQLiteDriver;

class SQLiteDriverTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @test
     */
    public function synopsis()
    {
        $this->assertTrue(is_array(SQLiteDriver::getOptions()));
        $drv = new SQLiteDriver(['pdo' => new PDO('sqlite::memory:')]);
        $this->assertTrue($drv instanceof Driver);

        /* SQLite doesn't support DB/Schema ops, so those should "fail" */
        $this->assertFalse($drv->getDatabaseNames());
        $this->assertFalse($drv->getSchemaNames(null));
        $this->assertFalse($drv->createDatabase(null));
        $this->assertFalse($drv->createSchema(null, null));
        $this->assertFalse($drv->dropDatabase(null));
        $this->assertFalse($drv->dropSchema(null, null));

        $table = new Table('test', [
            new Column('int', new Integer()),
            new Column('str', new String()),
            new Column('chr', new String(10, false, false)),
            new Column('flt', new Float()),
            new Column('bool', new Boolean()),
            new Column('ts', new DateTime()),
            new Column('dec', new Decimal()),
            new Column('enum', new Enum(['foo', 'bar'])), // Translated to string.
        ]);

        $this->assertTrue($drv->setTable(null, null, $table));
        $this->assertEquals(['test'], $drv->getTableNames(null, null));
        $tableModel = $drv->getTable(null, null, 'test');
        // XXX FIXME: test $tableModel a lot here!

        $this->assertTrue($drv->dropTable(null, null, 'test'));
        $this->assertEquals([], $drv->getTableNames(null, null));
    }

    /**
     * SQLite will take *anything* as a type. I would prefer to support only proper SQL types.
     */
    public function testUnsupportedTypes()
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->exec("CREATE TABLE whoathere (whatisthis SUPERCALIFRAGILISTICEXPIALIDOCIOUS)");
        $drv = new SQLiteDriver(['pdo' => $pdo]);
        try {
            $drv->getTable(null, null, 'whoathere');
            $this->fail("We should hit an unknown type.");
        } catch (DriverException $e) {
            // Expected
        }

        /* SQLite can pretty much name a column whatever you want. */
    }
}
