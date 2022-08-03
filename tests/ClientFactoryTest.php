<?php

//declare(strict_types=1);

namespace CNICTEST;

use CNIC\ClientFactory as CF;
use CNIC\HEXONET\Client as CL;

final class ClientFactoryTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var \CNIC\HEXONET\SessionClient|null $cl
     */
    public static $cl;
    /**
     * @var string user name
     */
    public static $user;
    /**
     * @var string password
     */
    public static $pw;

    public static function setUpBeforeClass(): void
    {
        //session_start();
        self::$user = getenv("TESTS_USER_CNR") ?: "";
        self::$pw = getenv("TESTS_USERPASSWORD_CNR") ?: "";
    }

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
            "username" => self::$user,
            "password" => self::$pw,
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
     * Basic test for getClient with Registrar CNR
     * @return void
     */
    public function testCNRClient()
    {
        $cl = CF::getClient([
            "registrar" => "CNR"
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
