<?php

namespace Tailor\Driver\Tests;

use Tailor\Model\Table;
use Tailor\Model\Column;
use Tailor\Model\Types\Integer;
use Tailor\Driver\Driver;
use Tailor\Driver\JSONDriver;

class JSONDriverTest extends \PHPUnit_Framework_TestCase
{
    public function testLoad()
    {
        /* Loading a non-existant file should fail. */
        $drv = new JSONDriver("/path/to/file/that/doesnt/exist");
        $this->assertFalse($drv->getDatabaseNames());
        $this->assertFalse($drv->getSchemaNames('foo'));
        $this->assertFalse($drv->getTableNames('foo', 'bar'));
        $this->assertFalse($drv->getTable('foo', 'bar', 'baz'));

        /* Loading a file with invalid content should fail. */
        $testFile = tempnam(sys_get_temp_dir(), "JSDTest");
        file_put_contents($testFile, "invalid");
        $drv = new JSONDriver($testFile);
        $this->assertFalse($drv->getDatabaseNames());

        /* Loading a file with valid JSON should succeed. */
        file_put_contents($testFile, json_encode(["foo" => []]));
        $databases = $drv->getDatabaseNames();
        $this->assertEquals(['foo'], $databases);

        /* Re-loading should return previously loaded data. */
        unlink($testFile);
        $this->assertEquals(['foo'], $drv->getDatabaseNames());
    }

    public function testSave()
    {
        /* This is expected to fail */
        $drv = new JSONDriver("/path/to/file/that/doesnt/exist");
        $drv->setTable('foo', 'bar', new Table());
        $this->assertFalse(is_file("/path/to/file/that/doesnt/exist"));

        /* Start tests that try to write to a real (temp) file. */
        $testFile = tempnam(sys_get_temp_dir(), "JSDTest");
        $drv = new JSONDriver($testFile);

        /* Set data to something that will cause json_encode to fail. */
        $sockets = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        $drv->setData($sockets);
        $drv->setTable('foo', 'bar', new Table());
        $this->assertEmpty(file_get_contents($testFile));
        $drv->setData(null);

        /* Now write something */
        $tbl = new Table("tbl", [new Column("col", new Integer(4))]);
        $drv->setTable('foo', 'bar', $tbl);
        $this->assertEquals(['foo'], $drv->getDatabaseNames(), 'Adding table should add database');
        $this->assertEquals(['bar'], $drv->getSchemaNames('foo'), 'Adding table should add schema');
        $this->assertEquals(['tbl'], $drv->getTableNames('foo', 'bar'), 'Adding table should index table');
        $this->assertFalse($drv->getSchemaNames('baz'), 'Non-existant db must cause failure');
        $this->assertFalse($drv->getTableNames('baz', 'boz'), 'Non-existant schema must cause failure');

        $data = json_decode(file_get_contents($testFile));

        $this->assertTrue(isset($data->foo->bar->tbl));
    }

    public function testGetTable()
    {
        $testFile = tempnam(sys_get_temp_dir(), "JSDTest");
        $drv = new JSONDriver($testFile);

        $tbl = new Table("tbl", [new Column("col", new Integer(4))]);
        $drv->setTable('foo', 'bar', $tbl);
        $this->assertFalse($drv->getTable('foo', 'bar', 'baz'), 'Fetch for non-existant table must fail');
        $tbl2 = $drv->getTable('foo', 'bar', 'tbl');
        $this->assertTrue($tbl->equals($tbl2));
    }

    public function testDatabaseOperations()
    {
        $testFile = tempnam(sys_get_temp_dir(), "JSDTest");
        $drv = new JSONDriver($testFile);
        $drv->setData([]);

        /* Test createDatabase */
        $this->assertEquals([], $drv->getDatabaseNames());
        $this->assertTrue($drv->createDatabase('foo'));
        $this->assertEquals(['foo'], $drv->getDatabaseNames());

        /* Test createSchema */
        $this->assertEquals([], $drv->getSchemaNames('foo'));
        $this->assertTrue($drv->createSchema('foo', 'bar'));
        $this->assertFalse($drv->createSchema('bar', 'foo'), 'schema create expected to fail without database');

        /* Test dropTable */
        $tbl = new Table('baz', [new Column('pri', new Integer(4))]);
        $this->assertTrue($drv->setTable('foo', 'bar', $tbl));
        $this->assertTrue($drv->dropTable('foo', 'bar', 'baz'));

        /* Test dropSchema */
        $this->assertTrue($drv->dropSchema('foo', 'bar'));

        $this->assertTrue($drv->dropDatabase('foo'));
    }
}
