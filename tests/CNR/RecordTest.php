<?php

//declare(strict_types=1);

namespace CNICTEST\CNR;

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
        $rec = new \CNIC\CNR\Record($d);
        $this->assertEquals($d, $rec->getData());
    }

    public function testGetDataByKey(): void
    {
        $rec = new \CNIC\CNR\Record([
            "DOMAIN" => "mydomain.com",
            "RATING" => "1",
            "RNDINT" => "321",
            "SUM"    => "1"
        ]);
        $this->assertNull($rec->getDataByKey("KEYNOTEXISTING"));
    }
}
