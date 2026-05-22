<?php

declare(strict_types=1);

//declare(strict_types=1);
namespace CNICTEST\CNR;

use CNIC\CNR\Column;
use PHPUnit\Framework\TestCase;

final class ColumnTest extends TestCase
{
    public function testGetKey(): void
    {
        $col = new Column("DOMAIN", ["mydomain1.com", "mydomain2.com", "mydomain3.com"]);
        $this->assertEquals("DOMAIN", $col->getKey());
    }
}
