<?php

declare(strict_types=1);

//declare(strict_types=1);
namespace CNICTEST\CNR;

use CNIC\CNR\Record;
use PHPUnit\Framework\TestCase;

final class RecordTest extends TestCase
{
    public function testGetData(): void
    {
        $d = [
            "DOMAIN" => "mydomain.com",
            "RATING" => "1",
            "RNDINT" => "321",
            "SUM"    => "1"
        ];
        $rec = new Record($d);
        $this->assertEquals($d, $rec->getData());
    }

    public function testGetDataByKey(): void
    {
        $rec = new Record([
            "DOMAIN" => "mydomain.com",
            "RATING" => "1",
            "RNDINT" => "321",
            "SUM"    => "1"
        ]);
        $this->assertNull($rec->getDataByKey("KEYNOTEXISTING"));
    }
}
