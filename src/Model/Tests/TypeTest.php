<?php

namespace Tailor\Model\Tests;

use \InvalidArgumentException;
use Tailor\Model\Types\Enum;
use Tailor\Model\Types\Integer;
use Tailor\Model\Types\String;
use Tailor\Model\Types\Float;
use Tailor\Model\Types\DateTime;

class TypeTest extends \PHPUnit_Framework_TestCase
{
    public function testInteger()
    {
        $int = new Integer();
        $this->assertEquals(4, $int->size);
        $this->assertFalse($int->unsigned);

        $int = new Integer(8, true);
        $this->assertEquals(8, $int->size);
        $this->assertTrue($int->unsigned);
    }

    public function testEnum()
    {
        $enum = new Enum();
        $this->assertEmpty($enum->values);

        $enum = new Enum(['a', 'b', 'c']);
        $this->assertEquals(['a', 'b', 'c'], $enum->values);
    }

    public function testString()
    {
        $str1 = new String(10, true, true);
        $this->assertEquals(10, $str1->length);
        $this->assertTrue($str1->binary);
        $this->assertTrue($str1->variable);

        $str2 = new String(20, false, false);
        $this->assertEquals(20, $str2->length);
        $this->assertFalse($str2->binary);
        $this->assertFalse($str2->variable);
    }

    public function testFloat()
    {
        $flt = new Float(8);
        $this->assertEquals(8, $flt->size);
    }

    public function testDateTime()
    {
        $datetimezone = new DateTime(false, true, true);
        $this->assertFalse($datetimezone->date);
        $this->assertTrue($datetimezone->time);
        $this->assertTrue($datetimezone->zone);

        $datetimezone = new DateTime(true, false, false);
        $this->assertTrue($datetimezone->date);
        $this->assertFalse($datetimezone->time);
        $this->assertFalse($datetimezone->zone);

        try {
            $invalid = new DateTIme(false, false, false);
            $this->fail("DateTime should have failed: Either date or time is required");
        } catch (InvalidArgumentException $e) {
            /* Ignored */
        }
    }
}
