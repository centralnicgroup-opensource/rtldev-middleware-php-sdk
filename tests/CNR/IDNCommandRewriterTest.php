<?php

declare(strict_types=1);

/**
 * CNICTEST\CNR
 * Copyright © Team Internet Group PLC
 */

namespace CNICTEST\CNR;

use CNIC\CNR\IDNCommandRewriter as R;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for CNIC\CNR\IDNCommandRewriter.
 *
 * These rules used to live on AbstractClient::autoIDNConvert() and were only
 * reachable through reflection on a factory-built client (RSRMID-2922). They are
 * now a module with its own public surface, so every rule — including the
 * OBJECTID/OBJECTCLASS special case — is asserted directly, with no client and no
 * reflection.
 *
 * There is no ext-intl skip: the extension is a hard composer requirement
 * (RSRMID-2977), so `idn_to_ascii()` is guaranteed and every case below runs
 * unconditionally. The same holds for CNICTEST\AbstractClientIDNTest, which
 * converts through the same vendor code.
 */
final class IDNCommandRewriterTest extends TestCase
{
    public function testConvertsMatchingKeys(): void
    {
        $out = R::rewrite([
            "NAMESERVER0" => "ns1.münchen.de",
            "DNSZONE" => "münchen.de",
            "PARENTDOMAIN" => "köln.example",
        ]);

        $this->assertSame("ns1.xn--mnchen-3ya.de", $out["NAMESERVER0"]);
        $this->assertSame("xn--mnchen-3ya.de", $out["DNSZONE"]);
        $this->assertSame("xn--kln-sna.example", $out["PARENTDOMAIN"]);
    }

    public function testConvertsTheShortNsKeyForm(): void
    {
        // RSRBE-7149: NS/NS<n> is the short form of the nameserver parameter.
        $out = R::rewrite(["NS1" => "ns1.münchen.de"]);
        $this->assertSame("ns1.xn--mnchen-3ya.de", $out["NS1"]);
    }

    public function testLeavesAsciiValuesUntouched(): void
    {
        // The API converts DOMAIN params itself, and an ASCII value has nothing
        // to convert — the command must reach the wire byte-identical.
        $cmd = ["NAMESERVER0" => "ns1.example.com", "DNSZONE" => "example.com"];
        $this->assertSame($cmd, R::rewrite($cmd));
    }

    public function testIgnoresNonMatchingKeys(): void
    {
        $cmd = ["FOO" => "münchen.de", "COMMAND" => "AddDomain"];
        $this->assertSame($cmd, R::rewrite($cmd));
    }

    public function testMatchesKeysCaseInsensitively(): void
    {
        $out = R::rewrite(["dnszone" => "münchen.de"]);
        $this->assertSame("xn--mnchen-3ya.de", $out["dnszone"]);
    }

    public function testConvertsObjectIdForMatchingObjectClass(): void
    {
        // RSRTPM-3167: OBJECTID is a pattern in the CNR API and does not accept
        // IDNs, so it is converted — but only when OBJECTCLASS says it holds a
        // domain-like object.
        $out = R::rewrite([
            "OBJECTID" => "münchen.de",
            "OBJECTCLASS" => "DOMAIN",
        ]);
        $this->assertSame("xn--mnchen-3ya.de", $out["OBJECTID"]);
    }

    public function testSkipsObjectIdForUnrelatedObjectClass(): void
    {
        $cmd = ["OBJECTID" => "münchen.de", "OBJECTCLASS" => "CONTACT"];
        $this->assertSame($cmd, R::rewrite($cmd));
    }

    public function testSkipsObjectIdWhenObjectClassIsAbsent(): void
    {
        $cmd = ["OBJECTID" => "münchen.de"];
        $this->assertSame($cmd, R::rewrite($cmd));
    }

    public function testPreservesKeyOrder(): void
    {
        // The rewrite happens after CommandFormatter's priority sort, so it must
        // rewrite values in place and never reorder keys.
        $out = R::rewrite([
            "COMMAND" => "AddDomain",
            "DNSZONE" => "münchen.de",
            "PARENTDOMAIN" => "köln.example",
        ]);
        $this->assertSame(["COMMAND", "DNSZONE", "PARENTDOMAIN"], array_keys($out));
    }

    public function testEmptyCommandIsReturnedUnchanged(): void
    {
        $this->assertSame([], R::rewrite([]));
    }
}
