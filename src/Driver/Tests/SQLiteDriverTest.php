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
            new Column('i', new Integer()),
            new Column('s', new String()),
            new Column('c', new String(10, false, false)),
            new Column('f', new Float()),
            //new Column('b', new Boolean()),
            new Column('t', new DateTime()),
            new Column('d', new Decimal()),
            //new Column('e', new Enum(['foo', 'bar'])), // Translated to string.
        ]);

        $this->assertTrue($drv->setTable(null, null, $table));
        $this->assertEquals(['test'], $drv->getTableNames(null, null));
        //var_dump($drv->getTable(null, null, 'test'));

        $this->assertTrue($drv->dropTable(null, null, 'test'));
        $this->assertEquals([], $drv->getTableNames(null, null));
    }
}
