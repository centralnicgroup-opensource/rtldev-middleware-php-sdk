<?php

declare(strict_types=1);

namespace CNICTEST\CNR;

use CNIC\CNR\SocketConfig as SC;
use PHPUnit\Framework\TestCase;

final class SocketConfigTest extends TestCase
{
    /**
     * test getPOSTData method
     */
    public function testGetPostData(): void
    {
        $d = (new SC())->getPOSTData();
        $this->assertEmpty($d);
    }

    /**
     * A command-level AUTH (EPP transfer authorization code) must be masked in
     * the secured POST body used for debug logging, exercised directly on
     * SocketConfig rather than through the Client (RSRMID-2938; mirrors the
     * IBS coverage in SocketConfigTest::testGetPOSTDataSecuredMasksTransferAuthInfo()).
     */
    public function testGetPostDataSecuredMasksAuth(): void
    {
        $sc = new SC();
        $raw = $sc->getPOSTData([
            "COMMAND" => "TransferDomain",
            "DOMAIN" => "example.com",
            "AUTH" => "sup3r-s3cr3t-auth",
        ], true);

        $this->assertStringContainsString("AUTH%3D%2A%2A%2A", $raw);
        $this->assertStringNotContainsString("sup3r-s3cr3t-auth", $raw);
    }
}
