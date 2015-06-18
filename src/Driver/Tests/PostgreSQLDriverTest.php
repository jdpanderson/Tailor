<?php

namespace Tailor\Driver\Tests;

use Tailor\Model\Table;
use Tailor\Driver\Driver;
use Tailor\Driver\PDO\PostgreSQL;
use Tailor\Driver\Tests\Fixtures\TestPDO;

class PostgreSQLDriverTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @test
     */
    public function synopsis()
    {
        $this->assertTrue(is_array(PostgreSQL::getOptions()));
        $drv = new PostgreSQL(['pdo' => new TestPDO()]);
        $this->assertTrue($drv instanceof Driver);

        /* PostgreSQL support hasn't been written yet. Currently everything fails. */
        $this->assertFalse($drv->getDatabaseNames());
        $this->assertFalse($drv->getSchemaNames(null));
        $this->assertFalse($drv->getTableNames(null, null));
        $this->assertFalse($drv->getTable(null, null, null));
        $this->assertFalse($drv->setTable(null, null, new Table(), false));
        $this->assertFalse($drv->createDatabase(null));
        $this->assertFalse($drv->createSchema(null, null));
        $this->assertFalse($drv->dropDatabase(null));
        $this->assertFalse($drv->dropSchema(null, null));
        $this->assertFalse($drv->dropTable(null, null, null));
    }
}
