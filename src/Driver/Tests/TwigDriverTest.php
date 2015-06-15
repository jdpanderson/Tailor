<?php

namespace Tailor\Driver\Tests;

use Tailor\Driver\TwigDriver;
use Tailor\Model\Table;
use Tailor\Model\Column;
use Twig_Environment;
use Twig_Loader_Array;

class TwigDriverTest extends \PHPUnit_Framework_TestCase
{

    // @codingStandardsIgnoreStart
    private static $testTemplates = [
        'test.md' => "{{database}}.{{schema}}.{{table.name}}:\n{%for column in table.columns%}*{{column.name}}\n{% endfor %}"
    ];
    // @codingStandardsIgnoreEnd

    private function getTwigEnv($templates)
    {
        return new Twig_Environment(new Twig_Loader_Array($templates));
    }

    public function testOutput()
    {
        $outfile = tempnam(sys_get_temp_dir(), "TDTest");
        $drv = new TwigDriver([
            TwigDriver::OPT_TWIG => $this->getTwigEnv(self::$testTemplates),
            TwigDriver::OPT_TEMPLATELIST => [$outfile => "test.md"],
        ]);

        $table = new Table("MyTable", [new Column("MyColumn")]);
        $drv->setTable('MyDatabase', 'MySchema', $table);

        $output = file_get_contents($outfile);

        $this->assertEquals("MyDatabase.MySchema.MyTable:\n*MyColumn\n", $output);
    }
}
