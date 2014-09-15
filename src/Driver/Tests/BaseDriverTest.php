<?php

namespace Tailor\Driver\Tests;

use Tailor\Model\Table;
use Tailor\Driver\Driver;
use Tailor\Driver\BaseDriver;

class BaseDriverTest extends \PHPUnit_Framework_TestCase
{
    public function testMethods()
    {
        $drv = new BaseDriver();
        $this->assertTrue($drv instanceof Driver);
        foreach (get_class_methods("Tailor\Driver\Driver") as $method) {
            $this->assertFalse($drv->$method(null, null, new Table()));
        }
    }
}
