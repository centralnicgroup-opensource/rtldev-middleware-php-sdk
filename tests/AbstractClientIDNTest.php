<?php

declare(strict_types=1);

/**
 * CNIC
 * Copyright © Team Internet Group PLC
 */

namespace CNICTEST;

use CNIC\ClientFactory as CF;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Covers the IDN handling that remains on the shared client: the public
 * `IDNConvert()`.
 *
 * Converting a caller-supplied list of names is brand-agnostic and has been
 * public API since v7.1.0, so it stayed on {@see \CNIC\AbstractClient} when the
 * *command* rewrite moved to {@see \CNIC\CNR\IDNCommandRewriter} in RSRMID-2922 —
 * which parameters of a command carry a domain name is CNR knowledge. Those rules
 * are covered by `tests/CNR/IDNCommandRewriterTest.php`, and their absence from
 * the shared tree by {@see ClientIDNSeamTest}.
 */
final class AbstractClientIDNTest extends TestCase
{
    public function testIDNConvertReturnsPunycodeForUnicodeDomains(): void
    {
        $result = CF::cnr()->IDNConvert(["münchen.de", "example.com"]);

        $this->assertSame("xn--mnchen-3ya.de", $result[0]["punycode"]);
        // already-ASCII names pass through unchanged
        $this->assertSame("example.com", $result[1]["punycode"]);
    }

    /**
     * Transitional processing is decided per TLD (idn-converter ^3, RSRMID-3015):
     * art|be|ca|de|swiss|fr|pm|re|tf|wf|yt keep deviation characters such as "ß",
     * every other TLD maps them ("ß" becomes "ss"). Only the last label decides.
     *
     * @return array<string, array{string, string}>
     */
    public static function transitionalProcessingProvider(): array
    {
        return [
            "non-transitional TLD" => ["faß.de", "xn--fa-hia.de"],
            "TLD match is case-insensitive" => ["faß.DE", "xn--fa-hia.de"],
            "transitional TLD" => ["faß.com", "fass.com"],
            "only the last label decides" => ["faß.de.com", "fass.de.com"],
            "already punycode is kept" => ["xn--fa-hia.de", "xn--fa-hia.de"],
        ];
    }

    #[DataProvider("transitionalProcessingProvider")]
    public function testIDNConvertAppliesTransitionalProcessingPerTld(string $domain, string $expected): void
    {
        $result = CF::cnr()->IDNConvert([$domain]);

        $this->assertSame($expected, $result[0]["punycode"]);
    }
}
