<?php

//declare(strict_types=1);

namespace CNICTEST\CNR;

final class ColumnTest extends \PHPUnit\Framework\TestCase
{
    public function testGetKey(): void
    {
        $col = new \CNIC\CNR\Column("DOMAIN", ["mydomain1.com", "mydomain2.com", "mydomain3.com"]);
        $this->assertEquals("DOMAIN", $col->getKey());
    }
}
