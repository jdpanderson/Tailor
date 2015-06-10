<?php

namespace Tailor\Driver\Tests;

use Tailor\Driver\Driver;
use Tailor\Driver\DriverException;
use Tailor\Driver\MySQLDriver;
use Tailor\Driver\Tests\Fixtures\TestPDO;
use Tailor\Model\Table;
use Tailor\Model\Column;
use Tailor\Model\Type;
use Tailor\Model\Types\Boolean;
use Tailor\Model\Types\Enum;
use Tailor\Model\Types\Integer;
use Tailor\Model\Types\String;
use Tailor\Model\Types\Float;
use Tailor\Model\Types\Decimal;
use Tailor\Model\Types\DateTime;
use PDO;
use PDOStatement;
use PDOException;
use Exception;

class MySQLDriverTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Store the last SQL passed to the mocked PDO::exec call
     *
     * @var string
     */
    public $lastExecSQL;

    // @codingStandardsIgnoreStart
    private static $describeTableData = [
        ['Field' => 'Id',         'Type' => 'int(11)',       'Null' => 'NO',  'Key' => 'PRI', 'Default' => 'NULL', 'Extra' => 'auto_increment'],
        ['Field' => 'Name',       'Type' => 'varchar(9)',    'Null' => 'NO',  'Key' => '',    'Default' => '',     'Extra' => ''],
        ['Field' => 'Country',    'Type' => 'char(8)',       'Null' => 'NO',  'Key' => 'UNI', 'Default' => '',     'Extra' => ''],
        ['Field' => 'District',   'Type' => 'tinyblob',      'Null' => 'YES', 'Key' => 'MUL', 'Default' => '',     'Extra' => ''],
        ['Field' => 'Population', 'Type' => 'decimal(10,2)', 'Null' => 'NO',  'Key' => '',    'Default' => 0,      'Extra' => ''],
        ['Field' => 'Average',    'Type' => 'float',         'Null' => 'YES', 'Key' => '',    'Default' => '',     'Extra' => ''],
        ['Field' => 'Update',     'Type' => 'timestamp',     'Null' => 'NO',  'Key' => '',    'Default' => '',     'Extra' => '']
    ];
    // @codingStandardsIgnoreEnd

    /**
     * Pass-through a result.
     *
     * False is passed through by the PDO object.
     * An exception is thrown on prepare/query.
     * Anything else will return a statement that will return the result on fetchAll
     */
    private function getPDOPassthrough($result, $execResult = null)
    {
        if ($result instanceof Exception) {
            $will = $this->throwException($result);
        } elseif ($result === false) {
            $will = $this->returnValue(false);
        } else {
            $stmt = $this->getMock('PDOStatement');
            $stmt->expects($this->any())
                ->method('fetchAll')
                ->will($this->returnValue($result));

            $will = $this->returnValue($stmt);
        }

        $pdo = $this->getMock('Tailor\Driver\Tests\Fixtures\TestPDO');
        $pdo->expects($this->any())
            ->method('prepare')
            ->will($will);

        $pdo->expects($this->any())
            ->method('query')
            ->will($will);

        $pdo->expects($this->any())
            ->method('quote')
            ->will($this->returnCallback(function($value) {
                /* This test isn't going to try to exploit itself. :) */
                return "\"{$value}\"";
            }));

        if (isset($execResult)) {
            if ($execResult instanceof Exception) {
                $execWill = $this->throwException($execResult);
            } else {
                // Like the following, but records the SQL passed to exec.
                //$execWill = $this->returnValue($execResult);
                $testCase = &$this;
                $execWill = $this->returnCallback(function($sql) use (&$testCase, $execResult) {
                    $testCase->lastExecSQL = $sql;
                    return $execResult;
                });
            }

            $pdo->expects($this->any())
                ->method('exec')
                ->will($execWill);
        }

        return $pdo;
    }
    public function testInterface()
    {
        $drv = new MySQLDriver(['pdo' => $this->getPDOPassthrough(false, false)]);
        $this->assertTrue($drv instanceof Driver);
    }

    public function testGetNames()
    {
        $drv = new MysQLDriver(['pdo' => $this->getPDOPassthrough(['foo'])]);
        $this->assertEquals(['foo'], $drv->getDatabaseNames());
        $this->assertEquals([Driver::SCHEMA_DEFAULT], $drv->getSchemaNames('ignored'));
        $this->assertEquals(['foo'], $drv->getTableNames('ignored', 'ignored'));

        $drv = new MySQLDriver(['pdo' => $this->getPDOPassthrough(false)]);
        $this->assertFalse($drv->getDatabaseNames());
        $this->assertFalse($drv->getSchemaNames('ignored'));
        $this->assertFalse($drv->getTableNames('ignored', 'ignored'));

        $drv = new MySQLDriver(['pdo' => $this->getPDOPassthrough(new PDOException())]);
        $this->assertFalse($drv->getDatabaseNames());
        $this->assertFalse($drv->getSchemaNames('ignored'));
        $this->assertFalse($drv->getTableNames('ignored', 'ignored'));
    }

    public function testCreate()
    {
        $drv = new MySQLDriver(['pdo' => $this->getPDOPassthrough(null, 1)]);
        $this->assertTrue($drv->createDatabase('ignored'));
        $this->assertTrue($drv->createSchema('ignored', 'ignored'));

        $drv = new MySQLDriver(['pdo' => $this->getPDOPassthrough(null, false)]);
        $this->assertFalse($drv->createDatabase('ignored'));
        $this->assertFalse($drv->createSchema('ignored', 'ignored'));

        $drv = new MySQLDriver(['pdo' => $this->getPDOPassthrough(null, new PDOException())]);
        $this->assertFalse($drv->createDatabase('ignored'));
        $this->assertFalse($drv->createSchema('ignored', 'ignored'));
    }

    public function testDrop()
    {
        $drv = new MySQLDriver(['pdo' => $this->getPDOPassthrough(null, 1)]);
        $this->assertTrue($drv->dropDatabase('ignored'));
        $this->assertFalse($drv->dropSchema('ignored', 'ignored')); /* Schemata not supported. */
        $this->assertTrue($drv->dropTable('ignored', 'ignored', 'ignored'));

        $drv = new MySQLDriver(['pdo' => $this->getPDOPassthrough(null, false)]);
        $this->assertFalse($drv->dropDatabase('ignored'));
        $this->assertFalse($drv->dropSchema('ignored', 'ignored'));
        $this->assertFalse($drv->dropTable('ignored', 'ignored', 'ignored'));

        $drv = new MySQLDriver(['pdo' => $this->getPDOPassthrough(null, new PDOException())]);
        $this->assertFalse($drv->dropDatabase('ignored'));
        $this->assertFalse($drv->dropSchema('ignored', 'ignored'));
        $this->assertFalse($drv->dropTable('ignored', 'ignored', 'ignored'));
    }

    public function testGetTable()
    {
        $drv = new MySQLDriver(['pdo' => $this->getPDOPassthrough(self::$describeTableData)]);

        $tbl = $drv->getTable('ignored', 'ignored', 'ignored');
        foreach (self::$describeTableData as $num => $colData) {
            $this->assertEquals($colData['Field'], $tbl->columns[$num]->name);
            $this->assertTrue($tbl->columns[$num]->type instanceof Type);
            if ($num) {
                $this->assertFalse($tbl->columns[$num]->primary);
            }
        }
        $this->assertTrue($tbl->columns[0]->type instanceof Integer);
        $this->assertTrue($tbl->columns[0]->primary);
        $this->assertTrue($tbl->columns[0]->sequence);
        $this->assertEquals(4, $tbl->columns[0]->type->size); // int -> 4 bytes

        $this->assertTrue($tbl->columns[2]->type instanceof String);
        $this->assertTrue($tbl->columns[2]->unique);
        $this->assertFalse($tbl->columns[2]->type->variable);
        $this->assertFalse($tbl->columns[2]->type->binary);
        $this->assertEquals(8, $tbl->columns[2]->type->length); // varchar(8)

        $this->assertTrue($tbl->columns[4]->type instanceof Decimal);
        $this->assertEquals(0, $tbl->columns[4]->default);
        $this->assertFalse($tbl->columns[4]->null);
        $this->assertTrue($tbl->columns[5]->type instanceof Float);
        $this->assertTrue($tbl->columns[5]->null);
        $this->assertTrue($tbl->columns[6]->type instanceof DateTime);
        // XXX assert a lot of things here. This does not cover every detail.
        //var_dump($tbl);

        /* Unhandled types */
        $data = self::$describeTableData;
        $data[] = ['Field' => 'Unsupported1', 'Type' => 'bit', 'Null' => 'NO', 'Key' => '', 'Default' => '', 'Extra' => ''];

        $drv = new MySQLDriver(['pdo' => $this->getPDOPassthrough($data)]);
        try {
            $tbl = $drv->getTable('ignored', 'ignored', 'ignored');
            $this->fail("bit isn't yet supported, so should throw exception");
        } catch (DriverException $e) {
        }

        /* Unhandled types */
        $data = self::$describeTableData;
        $data[] = ['Field' => 'Unknown1', 'Type' => 'fubar', 'Null' => 'NO', 'Key' => '', 'Default' => '', 'Extra' => ''];
        $drv = new MySQLDriver(['pdo' => $this->getPDOPassthrough($data)]);
        try {
            $tbl = $drv->getTable('ignored', 'ignored', 'ignored');
            $this->fail("fubar is not a known type, so should throw exception");
        } catch (DriverException $e) {
        }

        /* Imitate failure */
        $drv = new MySQLDriver(['pdo' => $this->getPDOPassthrough(false)]);
        $this->assertFalse($drv->getTable('ignored', 'ignored', 'ignored'));
    }

    public function testSetTable()
    {
        $pdo = $this->getPDOPassthrough(self::$describeTableData, 1);
        $drv = new MySQLDriver(['pdo' => $pdo]);
        $tbl = $drv->getTable('testDB1', 'ignored', 'testTbl1');
        $drv->setTable('ignored', 'ignored', $tbl);
        // Above should do nothing; No exec should happen.
        $this->assertNull($this->lastExecSQL);

        /* Trigger a create */
        $drv = new MySQLDriver(['pdo' => $this->getPDOPassthrough(false, 1)]);
        $drv->setTable('testDB2', 'ignored', $tbl);

        $this->assertEquals(
            // @codingStandardsIgnoreStart
            'CREATE TABLE `testDB2`.`testTbl1` (
`Id` INTEGER PRIMARY KEY AUTO_INCREMENT NOT NULL,
`Name` VARCHAR(9) NOT NULL DEFAULT "",
`Country` CHAR(8) NOT NULL DEFAULT "",
`District` TINYBLOB DEFAULT "",
`Population` DECIMAL(10, 2) NOT NULL DEFAULT "0",
`Average` FLOAT DEFAULT "",
`Update` TIMESTAMP NOT NULL DEFAULT ""
) ENGINE=InnoDB DEFAULT CHARSET=utf8',
            // @codingStandardsIgnoreEnd
            $this->lastExecSQL
        );

        $drv = new MySQLDriver(['pdo' => $this->getPDOPassthrough(self::$describeTableData, 1)]);

        /* Drop a column and create a new */
        $tbl->columns[2]->name = "blah";

        /* Trigger a modification */
        $tbl->columns[3]->null = false;
        $tbl->columns[3]->type = new String(255, true, false);
        $tbl->columns[3]->default = "foo";

        /* Create a whole lot of columns. */
        $tbl->columns[] = new Column('tinyblob', new String(255, true, true));
        $tbl->columns[] = new Column('blob', new String(10000, true, true));
        $tbl->columns[] = new Column('mediumtext', new String(65537, true, false));
        $tbl->columns[] = new Column('longblob', new String(pow(2, 28), true, true));
        $tbl->columns[] = new Column('int7', new Integer(7));
        $tbl->columns[] = new Column('float7', new Float(7));
        $tbl->columns[] = new Column('dt1', new DateTime(true, false, false));
        $tbl->columns[] = new Column('dt2', new DateTime(false, true, true));
        $tbl->columns[] = new Column('bool', new Boolean());
        $tbl->columns[] = new Column('enum', new Enum('a', 'b', 'c'));

        /* The above columns produce the SQL below, which I believe match up. */
        // ALTER TABLE `testDB3`.`testTbl1`
        // DROP COLUMN `Country`,
        // ADD COLUMN `blah` CHAR(8) NOT NULL DEFAULT "",
        // ADD COLUMN `tinyblob` TINYBLOB,
        // ADD COLUMN `blob` BLOB,
        // ADD COLUMN `mediumtext` MEDIUMTEXT,
        // ADD COLUMN `longblob` LONGBLOB,
        // ADD COLUMN `int7` BIGINT,
        // ADD COLUMN `float7` DOUBLE,
        // ADD COLUMN `dt1` DATE,
        // ADD COLUMN `dt2` TIME,
        // ADD COLUMN `bool` TINYINT(1) UNSIGNED,
        // ADD COLUMN `enum` ENUM("a","b","c"),
        // MODIFY `District` TINYTEXT NOT NULL DEFAULT "foo"

        $drv->setTable('testDB3', 'ignored', $tbl);
        // Assert that a bunch of SQL happened.

        $this->assertEquals(
            // @codingStandardsIgnoreStart
            'ALTER TABLE `testDB3`.`testTbl1` DROP COLUMN `Country`,ADD COLUMN `blah` CHAR(8) NOT NULL DEFAULT "",ADD COLUMN `tinyblob` TINYBLOB,ADD COLUMN `blob` BLOB,ADD COLUMN `mediumtext` MEDIUMTEXT,ADD COLUMN `longblob` LONGBLOB,ADD COLUMN `int7` BIGINT,ADD COLUMN `float7` DOUBLE,ADD COLUMN `dt1` DATE,ADD COLUMN `dt2` TIME,ADD COLUMN `bool` TINYINT(1) UNSIGNED,ADD COLUMN `enum` ENUM("a","b","c"),MODIFY `District` TINYTEXT NOT NULL DEFAULT "foo"',
            // @codingStandardsIgnoreEnd
            $this->lastExecSQL
        );
        $tbl->columns = [
            new Column('invalid', null)
        ];

        try {
            $drv->setTable('ignored', 'ignored', $tbl);
            $this->fail("Creating a column without a type should not work");
        } catch (DriverException $e) {
            /* Ignored */
        }
    }
}
