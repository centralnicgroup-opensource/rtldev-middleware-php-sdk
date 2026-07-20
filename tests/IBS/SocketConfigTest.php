<?php

declare(strict_types=1);

namespace CNICTEST\IBS;

use CNIC\IBS\SocketConfig as SC;
use PHPUnit\Framework\TestCase;

final class SocketConfigTest extends TestCase
{
    public function testGetPOSTDataNoCredentials(): void
    {
        $sc = new SC();
        $this->assertEquals("domain=test.com", $sc->getPOSTData(["domain" => "test.com"]));
    }

    public function testGetPOSTDataWithCredentials(): void
    {
        $sc = new SC();
        $sc->setLogin("myuser")->setPassword("mypw");
        $this->assertEquals(
            "domain=test.com&apikey=myuser&password=mypw",
            $sc->getPOSTData(["domain" => "test.com"])
        );
    }

    public function testGetPOSTDataSecured(): void
    {
        $sc = new SC();
        $sc->setLogin("myuser")->setPassword("mypw");
        $this->assertEquals(
            "domain=test.com&apikey=myuser&password=%2A%2A%2A",
            $sc->getPOSTData(["domain" => "test.com"], true)
        );
    }

    public function testGetPOSTDataSecuredMasksTransferAuthInfo(): void
    {
        // transferAuthInfo (domain auth code) must be masked in the secured
        // POST body used for debug logging, alongside the account password
        // (RSRMID-2897).
        $sc = new SC();
        $sc->setLogin("myuser")->setPassword("mypw");
        $raw = $sc->getPOSTData([
            "Domain" => "test.com",
            "transferAuthInfo" => "sup3r-s3cr3t-auth"
        ], true);
        $this->assertStringContainsString("transferAuthInfo=%2A%2A%2A", $raw);
        $this->assertStringContainsString("password=%2A%2A%2A", $raw);
        $this->assertStringNotContainsString("sup3r-s3cr3t-auth", $raw);
    }

    public function testGetPOSTDataUnsecuredKeepsTransferAuthInfo(): void
    {
        // On the actual wire request (not secured) the value must pass through.
        $sc = new SC();
        $raw = $sc->getPOSTData([
            "Domain" => "test.com",
            "transferAuthInfo" => "sup3r-s3cr3t-auth"
        ]);
        $this->assertStringContainsString("transferAuthInfo=sup3r-s3cr3t-auth", $raw);
    }

    public function testGetPOSTDataLoginOnly(): void
    {
        $sc = new SC();
        $sc->setLogin("myuser");
        $this->assertEquals(
            "domain=test.com&apikey=myuser",
            $sc->getPOSTData(["domain" => "test.com"])
        );
    }

    public function testGetPOSTDataPasswordOnly(): void
    {
        $sc = new SC();
        $sc->setPassword("mypw");
        $this->assertEquals(
            "domain=test.com&password=mypw",
            $sc->getPOSTData(["domain" => "test.com"])
        );
    }

    public function testSetLoginFluent(): void
    {
        $sc = new SC();
        $this->assertInstanceOf(SC::class, $sc->setLogin("myuser"));
    }

    public function testGetLogin(): void
    {
        $sc = new SC();
        $sc->setLogin("myuser");
        $this->assertEquals("myuser", $sc->getLogin());
    }

    public function testGetLoginEmpty(): void
    {
        $sc = new SC();
        $this->assertEquals("", $sc->getLogin());
    }

    public function testSetPasswordFluent(): void
    {
        $sc = new SC();
        $this->assertInstanceOf(SC::class, $sc->setPassword("mypw"));
    }

    public function testCommandParamsSpreadIntoQueryString(): void
    {
        // IBS sends individual key=value pairs — NOT wrapped in a single s_command= param like CNR
        $sc = new SC();
        $raw = $sc->getPOSTData(["domain" => "test.com", "type" => "A"]);
        $this->assertStringContainsString("domain=test.com", $raw);
        $this->assertStringContainsString("type=A", $raw);
        $this->assertStringNotContainsString("s_command", $raw);
    }
}
