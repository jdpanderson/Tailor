<?php

namespace Tailor\Driver\Tests;

use Tailor\Model\Table;
use Tailor\Driver\Driver;
use Tailor\Driver\BaseDriver;

class BaseDriverTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @test
     */
    public function synopsis()
    {
        $this->assertTrue(is_array(BaseDriver::getOptions()));
        $drv = new BaseDriver();
        $this->assertTrue($drv instanceof Driver);

        /* All methods in the base driver should "fail" */
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
