<?php

declare(strict_types=1);

namespace CNICTEST\IBS;

use CNIC\IBS\SocketConfig as SC;
use PHPUnit\Framework\TestCase;

final class SocketConfigTest extends TestCase
{
    /** @var array<string, string> */
    private static array $params = [
        "login"    => "apikey",
        "password" => "password",
    ];

    public function testGetPOSTDataNoCredentials(): void
    {
        $sc = new SC(self::$params);
        $this->assertEquals("domain=test.com", $sc->getPOSTData(["domain" => "test.com"]));
    }

    public function testGetPOSTDataWithCredentials(): void
    {
        $sc = new SC(self::$params);
        $sc->setLogin("myuser")->setPassword("mypw");
        $this->assertEquals(
            "domain=test.com&apikey=myuser&password=mypw",
            $sc->getPOSTData(["domain" => "test.com"])
        );
    }

    public function testGetPOSTDataSecured(): void
    {
        $sc = new SC(self::$params);
        $sc->setLogin("myuser")->setPassword("mypw");
        $this->assertEquals(
            "domain=test.com&apikey=myuser&password=%2A%2A%2A",
            $sc->getPOSTData(["domain" => "test.com"], true)
        );
    }

    public function testGetPOSTDataLoginOnly(): void
    {
        $sc = new SC(self::$params);
        $sc->setLogin("myuser");
        $this->assertEquals(
            "domain=test.com&apikey=myuser",
            $sc->getPOSTData(["domain" => "test.com"])
        );
    }

    public function testGetPOSTDataPasswordOnly(): void
    {
        $sc = new SC(self::$params);
        $sc->setPassword("mypw");
        $this->assertEquals(
            "domain=test.com&password=mypw",
            $sc->getPOSTData(["domain" => "test.com"])
        );
    }

    public function testSetLoginFluent(): void
    {
        $sc = new SC(self::$params);
        $this->assertInstanceOf(SC::class, $sc->setLogin("myuser"));
    }

    public function testGetLogin(): void
    {
        $sc = new SC(self::$params);
        $sc->setLogin("myuser");
        $this->assertEquals("myuser", $sc->getLogin());
    }

    public function testGetLoginEmpty(): void
    {
        $sc = new SC(self::$params);
        $this->assertEquals("", $sc->getLogin());
    }

    public function testSetPasswordFluent(): void
    {
        $sc = new SC(self::$params);
        $this->assertInstanceOf(SC::class, $sc->setPassword("mypw"));
    }

    public function testRemoteAddressExcludedWithoutIpFilterParam(): void
    {
        // IBS config.json has no "ipfilter" entry — remote address must not appear in POST data
        $sc = new SC(self::$params);
        $sc->setLogin("myuser")->setPassword("mypw")->setRemoteAddress("10.0.0.1");
        $this->assertEquals(
            "apikey=myuser&password=mypw",
            $sc->getPOSTData([])
        );
    }

    public function testRemoteAddressIncludedWithIpFilterParam(): void
    {
        // When parameters include an "ipfilter" key, the remote address is passed through
        $sc = new SC(array_merge(self::$params, ["ipfilter" => "clientip"]));
        $sc->setLogin("myuser")->setRemoteAddress("10.0.0.1");
        $this->assertStringContainsString("clientip=10.0.0.1", $sc->getPOSTData([]));
    }

    public function testCommandParamsSpreadIntoQueryString(): void
    {
        // IBS sends individual key=value pairs — NOT wrapped in a single s_command= param like CNR
        $sc = new SC(self::$params);
        $raw = $sc->getPOSTData(["domain" => "test.com", "type" => "A"]);
        $this->assertStringContainsString("domain=test.com", $raw);
        $this->assertStringContainsString("type=A", $raw);
        $this->assertStringNotContainsString("s_command", $raw);
    }

    public function testSetRemoteAddressFluent(): void
    {
        $sc = new SC(self::$params);
        $this->assertInstanceOf(SC::class, $sc->setRemoteAddress("10.0.0.1"));
    }
}
