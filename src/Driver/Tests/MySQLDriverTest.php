<?php

namespace Tailor\Driver\Tests;

use Tailor\Driver\Driver;
use Tailor\Driver\DriverException;
use Tailor\Driver\PDO\MySQL as MySQLDriver;
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
use ReflectionProperty;

/**
 * Test the MySQL driver against mocked data.
 *
 * No database servers were harmed in the making of these tests.
 */
class MySQLDriverTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Store the last SQL passed to the mocked query call
     *
     * @var string
     */
    public $lastQuerySQL;

    /**
     * Store the last SQL passed to the mocked exec call
     *
     * @var string
     */
    public $lastExecSQL;

    /**
     * Data copied from: $pdo->query("DESCRIBE <table>")->fetchAll(PDO::FETCH_ASSOC)
     *
     * @var string[]
     */
    // @codingStandardsIgnoreStart
    private static $describeTableData = [
        ['Field' => 'Id',         'Type' => 'int(11)',       'Null' => 'NO',  'Key' => 'PRI', 'Default' => 'NULL', 'Extra' => 'auto_increment'],
        ['Field' => 'Name',       'Type' => 'varchar(9)',    'Null' => 'NO',  'Key' => '',    'Default' => '',     'Extra' => ''],
        ['Field' => 'Country',    'Type' => 'char(8)',       'Null' => 'NO',  'Key' => 'UNI', 'Default' => '',     'Extra' => ''],
        ['Field' => 'District',   'Type' => 'tinyblob',      'Null' => 'YES', 'Key' => 'MUL', 'Default' => '',     'Extra' => ''],
        ['Field' => 'Population', 'Type' => 'decimal(10,2)', 'Null' => 'NO',  'Key' => '',    'Default' => 0,      'Extra' => ''],
        ['Field' => 'Average',    'Type' => 'float',         'Null' => 'YES', 'Key' => '',    'Default' => '',     'Extra' => ''],
        ['Field' => 'Update',     'Type' => 'timestamp',     'Null' => 'NO',  'Key' => '',    'Default' => '',     'Extra' => ''],
        ['Field' => 'Type',       'Type' => 'enum(\'foo\')', 'Null' => 'NO',  'Key' => '',    'Default' => 'foo',  'Extra' => ''],
    ];
    // @codingStandardsIgnoreEnd

    /**
     * Pass-through a result.
     *
     * False is passed through by the PDO object.
     * An exception is thrown on prepare/query.
     * Anything else will return a statement that will return the result on fetchAll
     */
    private function setupPassthrough($queryResult = null, $execResult = null)
    {

        /* Transform options into PHPUnit mock results. */
        foreach (['lastQuerySQL' => &$queryResult, 'lastExecSQL' => &$execResult] as $prop => &$opt) {
            if ($opt instanceof Exception) {
                $opt = $this->throwException($opt);
            } else {
                /* Use returnCallback to record the last executed SQL */
                $testCase = $this;
                $opt = $this->returnCallback(function ($sql) use (&$testCase, $prop, $opt) {
                    $testCase->$prop = $sql;
                    return $opt;
                });
            }
        }

        $drv = new MySQLDriver([MySQLDriver::OPT_PDO => new TestPDO()]);
        $runner = $this->getMockBuilder('Tailor\Util\PDORunner')->disableOriginalConstructor()->getMock();
        $runner->expects($this->any())->method('query')->will($queryResult);
        $runner->expects($this->any())->method('exec')->will($execResult);
        $runner->expects($this->any())->method('quote')->will($this->returnCallback(function ($str) {
            return "\"{$str}\""; // Hopefully this test won't try to exploit itself.
        }));
        $runnerProp = new ReflectionProperty($drv, 'pdoRunner');
        $runnerProp->setAccessible(true);
        $runnerProp->setValue($drv, $runner);

        return $drv;
    }

    public function testInterface()
    {
        $drv = $this->setupPassthrough(false, false);
        $this->assertTrue($drv instanceof Driver);
    }

    public function testGetNames()
    {
        $drv = $this->setupPassthrough(['foo']);
        $this->assertEquals(['foo'], $drv->getDatabaseNames());
        $this->assertEquals([Driver::SCHEMA_DEFAULT], $drv->getSchemaNames('ignored'));
        $this->assertEquals(['foo'], $drv->getTableNames('ignored', 'ignored'));
        $this->assertEquals(['foo'], $drv->getTableNames(Driver::DATABASE_DEFAULT, Driver::SCHEMA_DEFAULT));

        $drv = $this->setupPassthrough(false);
        $this->assertFalse($drv->getDatabaseNames());
        $this->assertFalse($drv->getSchemaNames('ignored'));
        $this->assertFalse($drv->getTableNames('ignored', 'ignored'));

        $drv = $this->setupPassthrough(new PDOException());
        $this->assertFalse($drv->getDatabaseNames());
        $this->assertFalse($drv->getSchemaNames('ignored'));
        $this->assertFalse($drv->getTableNames('ignored', 'ignored'));
    }

    public function testCreate()
    {
        /* Handle normal success */
        $drv =$this->setupPassthrough(null, 1);
        $this->assertTrue($drv->createDatabase('ignored'));
        $this->assertTrue($drv->createSchema('ignored', 'ignored'));

        /* Handle query failure */
        $drv = $this->setupPassthrough(null, false);
        $this->assertFalse($drv->createDatabase('ignored'));
        $this->assertFalse($drv->createSchema('ignored', 'ignored'));

        /* Ensure that an exception is handled */
        $drv = $this->setupPassthrough(null, new PDOException());
        $this->assertFalse($drv->createDatabase('ignored'));
        $this->assertFalse($drv->createSchema('ignored', 'ignored'));

        /* Can't create without real names */
        $this->assertFalse($drv->createDatabase(Driver::DATABASE_DEFAULT));
        $this->assertFalse($drv->createSchema(Driver::DATABASE_DEFAULT, Driver::SCHEMA_DEFAULT));
    }

    public function testDrop()
    {
        /* Test success */
        $drv = $this->setupPassthrough(null, 1);
        $this->assertTrue($drv->dropDatabase('ignored'));
        $this->assertFalse($drv->dropSchema('ignored', 'ignored')); /* Schemata not supported. */
        $this->assertTrue($drv->dropTable('ignored', 'ignored', 'ignored'));
        $this->assertTrue($drv->dropTable(Driver::DATABASE_DEFAULT, 'ignored', 'ignored'));
        $this->assertEquals('DROP TABLE `ignored`.`ignored`', $this->lastExecSQL);
        $this->assertTrue($drv->dropTable('ignored', Driver::SCHEMA_DEFAULT, 'ignored'));
        $this->assertEquals('DROP TABLE `ignored`.`ignored`', $this->lastExecSQL);
        $this->assertTrue($drv->dropTable(Driver::DATABASE_DEFAULT, Driver::SCHEMA_DEFAULT,  'ignored'));
        $this->assertEquals('DROP TABLE `ignored`', $this->lastExecSQL, "With default database/schema, drop should happen without database name");

        /* Dropping a database without providing a name should always fail. */
        $this->assertFalse($drv->dropDatabase(Driver::DATABASE_DEFAULT));

        /* Test that query failure is handled. */
        $drv = $this->setupPassthrough(null, false);
        $this->assertFalse($drv->dropDatabase('ignored'));
        $this->assertFalse($drv->dropSchema('ignored', 'ignored'));
        $this->assertFalse($drv->dropTable('ignored', 'ignored', 'ignored'));

        /* Test that exceptions are handled. */
        $drv = $this->setupPassthrough(null, new PDOException());
        $this->assertFalse($drv->dropDatabase('ignored'));
        $this->assertFalse($drv->dropSchema('ignored', 'ignored'));
        $this->assertFalse($drv->dropTable('ignored', 'ignored', 'ignored'));

    }

    public function testGetTable()
    {
        $drv = $this->setupPassthrough(self::$describeTableData);

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
        $this->assertFalse($tbl->columns[7]->null);
        $this->assertTrue($tbl->columns[7]->type instanceof Enum);
        $this->assertEquals('foo', $tbl->columns[7]->default);

        /* Do it again without database/schema names. Make sure it's identical. */
        $alt = $drv->getTable(Driver::DATABASE_DEFAULT, Driver::SCHEMA_DEFAULT, 'ignored');
        $this->assertEquals($tbl, $alt);

        // XXX assert a lot of things here. This does not cover every detail.
        //var_dump($tbl);

        /* Unhandled types */
        $data = self::$describeTableData;
        $data[] = [
            'Field' => 'Unsupported1',
            'Type' => 'bit',
            'Null' => 'NO',
            'Key' => '',
            'Default' => '',
            'Extra' => ''
        ];

        $drv = $this->setupPassthrough($data);
        try {
            $tbl = $drv->getTable('ignored', 'ignored', 'ignored');
            $this->fail("bit isn't yet supported, so should throw exception");
        } catch (DriverException $e) {
            /* Expected */
        }

        /* Unhandled types */
        $data = self::$describeTableData;
        $data[] = [
            'Field' => 'Unknown1',
            'Type' => 'fubar',
            'Null' => 'NO',
            'Key' => '',
            'Default' => '',
            'Extra' => ''
        ];
        $drv = $this->setupPassthrough($data);
        try {
            $tbl = $drv->getTable('ignored', 'ignored', 'ignored');
            $this->fail("fubar is not a known type, so should throw exception");
        } catch (DriverException $e) {
            /* Expected */
        }

        /* Imitate failure */
        $drv = $this->setupPassthrough(false);
        $this->assertFalse($drv->getTable('ignored', 'ignored', 'ignored'));
    }

    public function testSetTable()
    {
        $drv = $this->setupPassthrough(self::$describeTableData, 1);
        $tbl = $drv->getTable('testDB1', 'ignored', 'testTbl1');
        $this->lastExecSQL = null;
        $drv->setTable('ignored', 'ignored', $tbl);
        // Above should do nothing; No exec should happen.
        $this->assertNull($this->lastExecSQL);

        /* Trigger a create */
        $drv = $this->setupPassthrough(false, 1);
        $drv->setTable('testDB2', 'ignored', $tbl);

        // @codingStandardsIgnoreStart
        $createSQL = 'CREATE TABLE %TBL% (
`Id` INTEGER PRIMARY KEY AUTO_INCREMENT NOT NULL,
`Name` VARCHAR(9) NOT NULL DEFAULT "",
`Country` CHAR(8) NOT NULL DEFAULT "",
`District` TINYBLOB DEFAULT "",
`Population` DECIMAL(10, 2) NOT NULL DEFAULT "0",
`Average` FLOAT DEFAULT "",
`Update` TIMESTAMP NOT NULL DEFAULT "",
`Type` ENUM("foo") NOT NULL DEFAULT "foo"
) ENGINE=InnoDB DEFAULT CHARSET=utf8';
        // @codingStandardsIgnoreEnd

        $this->assertEquals(
            str_replace('%TBL%', '`testDB2`.`testTbl1`', $createSQL),
            $this->lastExecSQL
        );

        /* Without DB/schema, the create shouldn't specify a DB/schema */
        $drv->setTable(Driver::DATABASE_DEFAULT, Driver::SCHEMA_DEFAULT, $tbl);
        $this->assertEquals(str_replace('%TBL%', '`testTbl1`', $createSQL), $this->lastExecSQL);


        $drv = $this->setupPassthrough(self::$describeTableData, 1);

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
