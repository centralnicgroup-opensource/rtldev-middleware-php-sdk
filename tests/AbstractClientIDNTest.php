<?php

declare(strict_types=1);

namespace CNICTEST;

use CNIC\ClientFactory as CF;
use CNIC\CNR\Client as CNRClient;
use CNIC\IBS\Client as IBSClient;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the IDN handling on the clients.
 *
 * IDNConvert() and the protected autoIDNConvert() are otherwise only exercised
 * by the credentialed CNR integration suite. These tests drive them without any
 * live API: IDNConvert() runs against the bundled idn-converter library, and
 * autoIDNConvert() is invoked directly via reflection on a factory-built client.
 */
final class AbstractClientIDNTest extends TestCase
{
    #[\Override]
    protected function setUp(): void
    {
        if (!function_exists("idn_to_ascii")) {
            $this->markTestSkipped("ext-intl / idn_to_ascii not available");
        }
    }

    private function cnr(): CNRClient
    {
        $cl = CF::getClient("CNR");
        \assert($cl instanceof CNRClient);
        return $cl;
    }

    private function ibs(): IBSClient
    {
        $cl = CF::getClient("IBS");
        \assert($cl instanceof IBSClient);
        return $cl;
    }

    /**
     * Invoke the protected autoIDNConvert() on the given client.
     * @param array<string, string> $cmd
     * @return array<string, string>
     */
    private function invokeAutoIDNConvert(object $client, array $cmd): array
    {
        $m = new \ReflectionMethod($client, "autoIDNConvert");
        $m->setAccessible(true);
        /** @var array<string, string> $result */
        $result = $m->invoke($client, $cmd);
        return $result;
    }

    public function testIDNConvertReturnsPunycodeForUnicodeDomains(): void
    {
        $result = $this->cnr()->IDNConvert(["münchen.de", "example.com"]);

        $this->assertSame("xn--mnchen-3ya.de", $result[0]["punycode"]);
        // already-ASCII names pass through unchanged
        $this->assertSame("example.com", $result[1]["punycode"]);
    }

    public function testAutoIDNConvertConvertsMatchingKeys(): void
    {
        $out = $this->invokeAutoIDNConvert($this->cnr(), [
            "NAMESERVER0" => "ns1.münchen.de",
            "DNSZONE" => "münchen.de",
            "PARENTDOMAIN" => "köln.example",
        ]);

        $this->assertSame("ns1.xn--mnchen-3ya.de", $out["NAMESERVER0"]);
        $this->assertSame("xn--mnchen-3ya.de", $out["DNSZONE"]);
        $this->assertSame("xn--kln-sna.example", $out["PARENTDOMAIN"]);
    }

    public function testAutoIDNConvertLeavesAsciiValuesUntouched(): void
    {
        $cmd = ["NAMESERVER0" => "ns1.example.com"];
        $this->assertSame($cmd, $this->invokeAutoIDNConvert($this->cnr(), $cmd));
    }

    public function testAutoIDNConvertIgnoresNonMatchingKeys(): void
    {
        $cmd = ["FOO" => "münchen.de", "COMMAND" => "AddDomain"];
        $this->assertSame($cmd, $this->invokeAutoIDNConvert($this->cnr(), $cmd));
    }

    public function testAutoIDNConvertConvertsObjectIdOnlyForMatchingObjectClass(): void
    {
        $out = $this->invokeAutoIDNConvert($this->cnr(), [
            "OBJECTID" => "münchen.de",
            "OBJECTCLASS" => "DOMAIN",
        ]);
        $this->assertSame("xn--mnchen-3ya.de", $out["OBJECTID"]);
    }

    public function testAutoIDNConvertSkipsObjectIdForUnrelatedObjectClass(): void
    {
        $cmd = [
            "OBJECTID" => "münchen.de",
            "OBJECTCLASS" => "CONTACT",
        ];
        $this->assertSame($cmd, $this->invokeAutoIDNConvert($this->cnr(), $cmd));
    }

    public function testIbsAutoIDNConvertIsANoOp(): void
    {
        // IBS converts IDNs server-side, so the client must pass the command through verbatim.
        $cmd = ["NAMESERVER0" => "ns1.münchen.de", "DNSZONE" => "münchen.de"];
        $this->assertSame($cmd, $this->invokeAutoIDNConvert($this->ibs(), $cmd));
    }
}
