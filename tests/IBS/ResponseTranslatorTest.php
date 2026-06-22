<?php

declare(strict_types=1);

namespace CNICTEST\IBS;

use CNIC\IBS\Response as R;
use CNIC\IBS\ResponseParser as RP;
use CNIC\IBS\ResponseTranslator as RT;
use PHPUnit\Framework\TestCase;

final class ResponseTranslatorTest extends TestCase
{
    /**
     * Test placeholder vars replacement mechanism
     */
    public function testPlaceHolderReplacements(): void
    {
        // no placeholders left in response when none provided
        $r = new R("");
        $this->assertEquals(0, preg_match("/\{[A-Z_]+\}/", $r->getDescription()), "case 1");

        // placeholder replaced when provided
        $r = new R("", [], ["CONNECTION_URL" => "123HXPHFOUND123"]);
        $this->assertStringContainsString("123HXPHFOUND123", $r->getDescription(), "case 2");
    }

    /**
     * Test that literal lowercase brace content is left untouched
     * (the placeholder rewrite only targets uppercase {TOKEN} markers)
     */
    public function testLiteralLowercaseBracePreserved(): void
    {
        $spfValue = "v=spf1 exists:%{i}.spf.hc461-90.ca.test.com -all";
        $raw = RT::translate("status=SUCCESS\r\nmessage=" . $spfValue . "\r\n", []);
        $this->assertStringContainsString("message=" . $spfValue, $raw);
    }

    /**
     * Test that an empty raw response maps to the "empty" template
     */
    public function testEmptyResponseMapsToEmptyTemplate(): void
    {
        $raw = RT::translate("", []);
        $this->assertStringContainsString("status=FAILURE", $raw);
        $this->assertStringContainsString("Empty API response", $raw);
    }

    /**
     * Test explicit static template lookup by id
     */
    public function testStaticTemplateLookup(): void
    {
        $raw = RT::translate("403", []);
        $hash = RP::parse($raw);
        $this->assertSame("FAILURE", $hash["status"]);
        $this->assertSame("403 Forbidden", $hash["message"]);
    }

    /**
     * Test that the curl/HTTP error detail is injected into the {HTTPERROR} slot
     */
    public function testHttpErrorPlaceholderSubstituted(): void
    {
        $raw = RT::translate("httperror|Connection timed out", []);
        $this->assertStringContainsString("status=FAILURE", $raw);
        $this->assertStringContainsString("(Connection timed out)", $raw);
    }

    /**
     * Test that all "missing or empty status" variants map to the "invalid" template
     */
    public function testInvalidResponseBranches(): void
    {
        $expected = "423 Invalid API response. Contact Support";

        // JSON without status field
        $hash = RP::parse(RT::translate('{"somekey":"somevalue"}', []));
        $this->assertSame("FAILURE", $hash["status"], "json missing status");
        $this->assertSame($expected, $hash["message"], "json missing status");

        // plain text without status field
        $hash = RP::parse(RT::translate("somekey=somevalue\r\n", []));
        $this->assertSame("FAILURE", $hash["status"], "plain missing status");
        $this->assertSame($expected, $hash["message"], "plain missing status");

        // JSON with empty status
        $hash = RP::parse(RT::translate('{"status":""}', []));
        $this->assertSame("FAILURE", $hash["status"], "json empty status");
        $this->assertSame($expected, $hash["message"], "json empty status");

        // plain text with empty status
        $hash = RP::parse(RT::translate("status=\r\n", []));
        $this->assertSame("FAILURE", $hash["status"], "plain empty status");
        $this->assertSame($expected, $hash["message"], "plain empty status");
    }

    /**
     * Test that a response carrying a non-empty status is returned unchanged
     */
    public function testValidStatusPassesThrough(): void
    {
        $input = '{"status":"SUCCESS","domain":"ibstest.com"}';
        $this->assertSame($input, RT::translate($input, ["ResponseFormat" => "JSON"]));
    }
}
