<?php

declare(strict_types=1);

namespace CNICTEST\IBS;

use CNIC\IBS\ResponseParser as RP;
use PHPUnit\Framework\TestCase;

final class ResponseTest extends TestCase
{
    public function testParseResponseWithDates(): void
    {
        $raw = json_encode([
            "date" => "2021/12/31",
            "expirydate" => "2026/12/31",
            "paiduntil" => "2023/07/01",
            "EXPIRATION" => "2024/05/02"
        ]) ?: "";
        $expected = [
            'date' => '2021-12-31',
            'expirydate' => '2026-12-31',
            'paiduntil' => '2023-07-01',
            'EXPIRATION' => '2024-05-02'
        ];
        $this->assertSame($expected, RP::parse($raw));
    }

    public function testParseResponseWithSpecialCharacters(): void
    {
        $input = [
            "idcard" => "1122/23/12",
            "key2"   => "value-with-spaces",
            "key3"   => "value/with/slashes"
        ];
        $raw = json_encode($input) ?: "";
        $expected = $input;
        $this->assertSame($expected, RP::parse($raw));
    }

    public function testParseResponseWithMultipleEqualSigns(): void
    {
        $input = [
            "key1" => "value=with=multiple=equals",
            "key2" => "=value2"
        ];
        $raw = json_encode($input) ?: "";
        $expected = $input;
        $this->assertSame($expected, RP::parse($raw));
    }

    public function testParseResponseWithUtf8AndSpecialCharacters(): void
    {
        $input = [
            'name' => 'José',
            'emoji' => '😊',
            'symbols' => '©™®'
        ];
        $raw = json_encode($input) ?: "";
        $expected = $input;
        $this->assertSame($expected, RP::parse($raw));
    }

    public function testParseResponseWithNumericKeysAndValues(): void
    {
        $input = [
            '123' => '456',
            '789' => '012'
        ];
        $raw = json_encode($input) ?: "";
        $expected = $input;
        $this->assertSame($expected, RP::parse($raw));
    }

    public function testParseResponseWithSingleValidLine(): void
    {
        $input = [
            'key1' => 'value1'
        ];
        $raw = json_encode($input) ?: "";
        $expected = $input;
        $this->assertSame($expected, RP::parse($raw));
    }
}
