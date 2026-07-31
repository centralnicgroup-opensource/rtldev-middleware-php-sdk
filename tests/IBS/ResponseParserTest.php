<?php

declare(strict_types=1);

namespace CNICTEST\IBS;

use CNIC\IBS\ResponseParser as RP;
use CNIC\ResponseParserInterface;
use PHPUnit\Framework\TestCase;

/**
 * Direct tests for the IBS parser (used by Moniker alike).
 *
 * These used to live in ResponseTest.php; they never needed a Response, and
 * since RSRMID-2924 gave the parser a contract of its own they have a home that
 * says so. Response-level behaviour stays in ResponseTest.php.
 */
final class ResponseParserTest extends TestCase
{
    private function parser(): ResponseParserInterface
    {
        return new RP();
    }

    // --- JSON parsing ---

    public function testParseResponseWithDates(): void
    {
        // Raw date values survive verbatim — the parser no longer rewrites
        // "/" to "-" on date-suffixed keys (that normalization moved to
        // CNIC\ApiDateTime, which accepts both separators).
        $input = [
            "date" => "2021/12/31",
            "expirydate" => "2026/12/31",
            "paiduntil" => "2023/07/01",
            "EXPIRATION" => "2024/05/02"
        ];
        $raw = (string) json_encode($input);
        $this->assertSame($input, $this->parser()->parse($raw));
    }

    public function testParseDoesNotCorruptNonDateSlashValues(): void
    {
        // The old rewrite matched on key suffix alone and corrupted any "/"
        // value under a date-suffixed key, including non-date content such
        // as "n/a". It must now survive untouched.
        $input = [
            "updatedate" => "n/a",
            "paiduntil" => "n/a",
        ];
        $raw = (string) json_encode($input);
        $this->assertSame($input, $this->parser()->parse($raw));
    }

    public function testParseResponseWithSpecialCharacters(): void
    {
        $input = [
            "idcard" => "1122/23/12",
            "key2"   => "value-with-spaces",
            "key3"   => "value/with/slashes"
        ];
        $raw = (string) json_encode($input);
        $expected = $input;
        $this->assertSame($expected, $this->parser()->parse($raw));
    }

    public function testParseResponseWithMultipleEqualSigns(): void
    {
        $input = [
            "key1" => "value=with=multiple=equals",
            "key2" => "=value2"
        ];
        $raw = (string) json_encode($input);
        $expected = $input;
        $this->assertSame($expected, $this->parser()->parse($raw));
    }

    public function testParseResponseWithUtf8AndSpecialCharacters(): void
    {
        $input = [
            'name' => 'José',
            'emoji' => '😊',
            'symbols' => '©™®'
        ];
        $raw = (string) json_encode($input);
        $expected = $input;
        $this->assertSame($expected, $this->parser()->parse($raw));
    }

    public function testParseResponseWithNumericKeysAndValues(): void
    {
        $input = [
            '123' => '456',
            '789' => '012'
        ];
        $raw = (string) json_encode($input);
        $expected = $input;
        $this->assertSame($expected, $this->parser()->parse($raw));
    }

    public function testParseResponseWithSingleValidLine(): void
    {
        $input = [
            'key1' => 'value1'
        ];
        $raw = (string) json_encode($input);
        $expected = $input;
        $this->assertSame($expected, $this->parser()->parse($raw));
    }

    public function testParseNestedDatesSurviveVerbatim(): void
    {
        $input = [
            "domain" => "ibstest.com",
            "data" => [
                "expirationdate" => "2026/02/20",
                "nested" => ["paiduntil" => "2027/01/02"]
            ]
        ];
        $raw = (string) json_encode($input);
        $this->assertSame($input, $this->parser()->parse($raw));
    }

    public function testParseResponseFormatCaseInsensitive(): void
    {
        // a lowercase "json" ResponseFormat is still treated as JSON
        $result = $this->parser()->parse('{"status":"SUCCESS"}', ["ResponseFormat" => "json"]);
        $this->assertSame(["status" => "SUCCESS"], $result);
    }

    // --- plain text and invalid ---

    public function testParsePlainTextResponse(): void
    {
        $raw = "status=FAILURE\r\nmessage=403 Forbidden\r\n";
        $result = $this->parser()->parse($raw);
        $this->assertSame('FAILURE', $result['status']);
        $this->assertSame('403 Forbidden', $result['message']);
    }

    public function testParseInvalidResponse(): void
    {
        $raw = "this is not valid at all";
        $result = $this->parser()->parse($raw);
        $this->assertSame('FAILURE', $result['status']);
        $this->assertSame('423 Invalid API response. Contact Support', $result['message']);
    }

    public function testParseEmptyStringIsInvalid(): void
    {
        $result = $this->parser()->parse("");
        $this->assertSame('FAILURE', $result['status']);
        $this->assertSame('423 Invalid API response. Contact Support', $result['message']);
    }

    public function testParseScalarJsonIsInvalid(): void
    {
        // A bare valid JSON scalar (number, quoted string, boolean, float)
        // decodes to a non-array value. It must not fatal in
        // array_walk_recursive() but return the graceful invalid-response
        // fallback, same as any other unparseable response.
        foreach (["123", '"a string"', "true", "false", "1.5"] as $raw) {
            $result = $this->parser()->parse($raw);
            $this->assertSame('FAILURE', $result['status'], "raw: {$raw}");
            $this->assertSame('423 Invalid API response. Contact Support', $result['message'], "raw: {$raw}");
        }
    }

    public function testParseForcedPlainTextWithJsonPayload(): void
    {
        // an explicit non-JSON ResponseFormat forces the plain-text path,
        // so a JSON payload (no "key=value" lines) is treated as invalid
        $result = $this->parser()->parse('{"status":"SUCCESS"}', ["ResponseFormat" => "TEXT"]);
        $this->assertSame('FAILURE', $result['status']);
        $this->assertSame('423 Invalid API response. Contact Support', $result['message']);
    }

    public function testParseNonEmptyCmdWithoutResponseFormat(): void
    {
        // a non-empty command without ResponseFormat also forces the plain-text path
        $result = $this->parser()->parse('{"status":"SUCCESS"}', ["Command" => "QueryDomainList"]);
        $this->assertSame('FAILURE', $result['status']);
        $this->assertSame('423 Invalid API response. Contact Support', $result['message']);
    }

    public function testParseTemplateTextIsTheSameOnBothBranches(): void
    {
        // The template manager parses with no command (templates are not tied to
        // one), which selects the JSON branch; Response::populate() passes the
        // command it was built with, and a command *without* ResponseFormat
        // selects the plain-text branch. That is the actually divergent pair —
        // a command carrying ResponseFormat=JSON would take the same branch as
        // the empty one and prove nothing. For template text the two must agree,
        // because the JSON branch falls back to plain text when json_decode()
        // fails. This divergence used to be invisible. (Ref: RSRMID-2924.)
        $plain = "status=FAILURE\r\nmessage=403 Forbidden\r\n";
        $viaJsonBranch = $this->parser()->parse($plain);
        $viaPlainBranch = $this->parser()->parse($plain, ["Command" => "DomainInfo"]);

        $this->assertSame(["status" => "FAILURE", "message" => "403 Forbidden"], $viaJsonBranch);
        $this->assertSame($viaJsonBranch, $viaPlainBranch);
    }
}
