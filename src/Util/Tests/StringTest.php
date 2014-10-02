<?php

namespace Tailor\Util\Tests;

use Tailor\Util\String;

class StringTest extends \PHPUnit_Framework_TestCase
{
	public function testStripDelim()
	{
		$this->assertEquals(["test", "+"], String::stripDelim("'test'+"));
		$this->assertEquals(["te'st", "+"], String::stripDelim("'te''st'+"));

		$this->assertFalse(String::stripDelim("invalid"));
		$this->assertFalse(String::stripDelim("'invalid"));
	}

	public function testParseQuotedList()
	{
		$this->assertEquals(["a", "b", "c"], String::parseQuotedList("'a','b','c'"));
		$this->assertEquals(["a"], String::parseQuotedList("'a'"));
		$this->assertEquals(["a","b'c","'d'"], String::parseQuotedList("'a','b''c','''d'''"));

		$this->assertEquals(false, String::parseQuotedList("invalid"));
		$this->assertEquals(false, String::parseQuotedList("'valid',invalid"));
	}
}