<?php

declare(strict_types=1);

namespace CNICTEST\MONIKER;

use CNIC\IBS\ResponseParser as RP;
use CNIC\ResponseParserInterface;
use PHPUnit\Framework\TestCase;

/**
 * Moniker runs on the IBS platform and therefore on the IBS parser; this
 * mirrors tests/IBS/ResponseParserTest.php on purpose (see CLAUDE.md) so a
 * Moniker-only regression in the shared parser is visible under the Moniker
 * suite too.
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
        // Raw date values survive verbatim (see tests/IBS/ResponseParserTest.php).
        $input = [
            "date" => "2021/12/31",
            "expirydate" => "2026/12/31",
            "paiduntil" => "2023/07/01",
            "EXPIRATION" => "2024/05/02"
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
}
