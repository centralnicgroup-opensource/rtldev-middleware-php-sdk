<?php

//declare(strict_types=1);

namespace CNICTEST;

use CNIC\ClientFactory as CF;
use CNIC\HEXONET\Client as CL;

final class ClientFactoryTest extends \PHPUnit\Framework\TestCase
{

    public function testHexonetClient()
    {
        $cl = CF::getClient("HEXONET");
        $this->assertInstanceOf(CL::class, $cl);
    }

    public function testRRPproxyClient()
    {
        $cl = CF::getClient("RRPproxy");
        $this->assertInstanceOf(CL::class, $cl);
    }

    public function testInvalidClient()
    {
        $this->expectException(\Exception::class);
        $cl = CF::getClient("InvalidRegistrar");
    }
}
