<?php

declare(strict_types=1);

//declare(strict_types=1);
namespace CNICTEST;

use CNIC\ClientFactory as CF;
use CNIC\CNR\Client as CL;
use CNIC\CNR\SessionClient;
use PHPUnit\Framework\TestCase;

final class ClientFactoryTest extends TestCase
{
    public static ?SessionClient $cl = null;
    /**
     * @var string user name
     */
    public static string $user;
    /**
     * @var string password
     */
    public static string $pw;

    #[\Override]
    public static function setUpBeforeClass(): void
    {
        //session_start();
        self::$user = (string) getenv("RTLDEV_MW_CI_USER_CNR");
        self::$pw = (string) getenv("RTLDEV_MW_CI_USERPASSWORD_CNR");
    }

    /**
     * Extended Basic test for getClient with Registrar CNR
     */
    public function testCnrClient1(): void
    {
        $cl = CF::getClient([
            "registrar" => "CNR",
            "username" => self::$user,
            "password" => self::$pw,
            "sandbox" => true,
            "referer" => "https://www.centralnicreseller.com",
            "ua" => [
                "name" => "WHMCS",
                "version" => "8.2.0",
                "modules" => [
                    "cnic" => "7.0.4"
                ]
            ],
            "proxyserver" => "http://192.168.2.31",
            "logging" => true
        ]);
        $this->assertInstanceOf(CL::class, $cl);
    }

    /**
     * Basic test for getClient with Registrar CNR
     */
    public function testCnrClient2(): void
    {
        $cl = CF::getClient([
            "registrar" => "CNR"
        ]);
        $this->assertInstanceOf(CL::class, $cl);
    }

    /**
     * Basic test for getClient with invalid Registrar ID
     */
    public function testInvalidClient(): void
    {
        $this->expectException(\Exception::class);
        CF::getClient([
            "registrar" => "InvalidRegistrar"
        ]);
    }
}
