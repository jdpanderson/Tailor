<?php

namespace Tailor\Model\Tests;

use Tailor\Model\Table;
use Tailor\Model\Column;
use Tailor\Model\Types\Integer;

class TableTest extends \PHPUnit_Framework_TestCase
{
    public function testEquals()
    {
        $table = new Table("foo", [new Column()]);
        $table->columns[0]->name = "foo";

        $tableCopy = clone($table);

        $this->assertFalse($table === $tableCopy);
        $this->assertTrue($table->equals($tableCopy));
        $tableCopy->name = "bar";
        $this->assertFalse($table->equals($tableCopy));
        $tableCopy->name = $table->name;
        $tableCopy->columns[0]->name = "baz";
        $this->assertFalse($table->equals($tableCopy));
    }
}
