<?php

namespace Tailor\Model\Tests;

use Tailor\Model\Type;
use Tailor\Model\Types\Integer;
use Tailor\Model\Types\BaseType;

class BaseTypeTest extends \PHPUnit_Framework_TestCase
{
    public function testMethods()
    {
        $typ1 = new BaseType();
        $typ2 = new Integer();
        $typ3 = new Integer();
        $typ2->size = 4;
        $typ3->size = 8;
        $this->assertFalse($typ1->equals($typ2));
        $this->assertTrue($typ1->equals($typ1));
        $this->assertFalse($typ2->equals($typ3));
    }
}
