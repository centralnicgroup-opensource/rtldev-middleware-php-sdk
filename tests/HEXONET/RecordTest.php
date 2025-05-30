<?php

//declare(strict_types=1);

namespace CNICTEST\HEXONET;

final class RecordTest extends \PHPUnit\Framework\TestCase
{
    public function testGetData(): void
    {
        $d = [
            "DOMAIN" => "mydomain.com",
            "RATING" => "1",
            "RNDINT" => "321",
            "SUM"    => "1"
        ];
        $rec = new \CNIC\HEXONET\Record($d);
        $this->assertEquals($d, $rec->getData());
    }

    public function testGetDataByKey(): void
    {
        $rec = new \CNIC\HEXONET\Record([
            "DOMAIN" => "mydomain.com",
            "RATING" => "1",
            "RNDINT" => "321",
            "SUM"    => "1"
        ]);
        $this->assertNull($rec->getDataByKey("KEYNOTEXISTING"));
    }
}
