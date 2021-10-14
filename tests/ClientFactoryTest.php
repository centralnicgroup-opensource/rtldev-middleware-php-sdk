<?php

//declare(strict_types=1);

namespace CNICTEST;

use CNIC\ClientFactory as CF;
use CNIC\HEXONET\Client as CL;

final class ClientFactoryTest extends \PHPUnit\Framework\TestCase
{

    /**
     * Basic test for getClient with Registrar HEXONET
     * @return void
     */
    public function testHexonetClient1()
    {
        $cl = CF::getClient([
            "registrar" => "HEXONET"
        ]);
        $this->assertInstanceOf(CL::class, $cl);
    }

    /**
     * Extended Basic test for getClient with Registrar HEXONET
     * @return void
     */
    public function testHexonetClient2()
    {
        $cl = CF::getClient([
            "registrar" => "HEXONET",
            "username" => "test.user",
            "password" => "test.passw0rd",
            "sandbox" => true,
            "referer" => "https://www.hexonet.net",
            "ua" => [
                "name" => "WHMCS",
                "version" => "8.2.0",
                "modules" => [
                    "ispapi" => "7.0.4"
                ]
            ],
            "proxyserver" => "http://192.168.2.31",
            "logging" => true
        ]);
        $this->assertInstanceOf(CL::class, $cl);
    }

    /**
     * Basic test for getClient with Registrar RRPproxy
     * @return void
     */
    public function testRRPproxyClient()
    {
        $cl = CF::getClient([
            "registrar" => "RRPproxy"
        ]);
        $this->assertInstanceOf(CL::class, $cl);
    }

    /**
     * Basic test for getClient with invalid Registrar ID
     * @return void
     */
    public function testInvalidClient()
    {
        $this->expectException(\Exception::class);
        $cl = CF::getClient([
            "registrar" => "InvalidRegistrar"
        ]);
    }
}
