<?php

namespace Tailor\Model\Tests;

use Tailor\Model\Column;
use Tailor\Model\Types\Integer;

class ColumnTest extends \PHPUnit_Framework_TestCase
{
    public function testEquals()
    {
        $col = new Column("foo", new Integer(4, false));
        $col->default = "bar";

        $colCopy = clone($col);

        $this->assertFalse($col === $colCopy);
        $this->assertTrue($col->equals($colCopy));
        $colCopy->default = "baz";
        $this->assertFalse($col->equals($colCopy));
    }
}
