<?php

namespace Tailor\Driver\Tests\Fixtures;

use \PDO;

/**
 * Work around PHPUnit's inability to directly mock PDO objects.
 */
class TestPDO extends PDO
{
    public function __construct()
    {
    }

    public function setAttribute($attr, $value)
    {
    }
}
