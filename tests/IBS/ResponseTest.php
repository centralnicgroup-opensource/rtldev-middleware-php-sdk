<?php

declare(strict_types=1);

namespace CNICTEST\IBS;

use CNIC\IBS\ResponseParser as RP;
use PHPUnit\Framework\TestCase;

final class ResponseTest extends TestCase
{
    public function testParseResponseWithDates(): void
    {
        $raw = "date=2021/12/31\nexpirydate=2026/12/31\npaiduntil=2023/07/01\nEXPIRATION=2024/05/02";
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
        $raw = "idcard=1122/23/12\nkey2=value-with-spaces\nkey3=value/with/slashes";
        $expected = [
            'idcard' => '1122/23/12',
            'key2' => 'value-with-spaces',
            'key3' => 'value/with/slashes'
        ];
        $this->assertSame($expected, RP::parse($raw));
    }

    public function testParseResponseWithMultipleEqualSigns(): void
    {
        $raw = "key1=value=with=multiple=equals\nkey2==value2";
        $expected = [
            'key1' => 'value=with=multiple=equals',
            'key2' => '=value2'
        ];
        $this->assertSame($expected, RP::parse($raw));
    }

    public function testParseResponseWithUtf8AndSpecialCharacters(): void
    {
        $raw = "name=José\nemoji=😊\nsymbols=©™®";
        $expected = [
            'name' => 'José',
            'emoji' => '😊',
            'symbols' => '©™®'
        ];
        $this->assertSame($expected, RP::parse($raw));
    }

    public function testParseResponseWithNumericKeysAndValues(): void
    {
        $raw = "123=456\n789=012";
        $expected = [
            '123' => '456',
            '789' => '012'
        ];
        $this->assertSame($expected, RP::parse($raw));
    }

    public function testParseResponseWithSpacesInKeysAndValues(): void
    {
        $raw = " key1 = value1 \n  key2=value2  \n key3 =  value3 ";
        $expected = [
            'key1' => 'value1',
            'key2' => 'value2',
            'key3' => 'value3'
        ];
        $this->assertSame($expected, RP::parse($raw));
    }

    public function testParseResponseWithDifferentLineBreaks(): void
    {
        $raw = "key1=value1\r\nkey2=value2\nkey3=value3\r\nkey4=value4";
        $expected = [
            'key1' => 'value1',
            'key2' => 'value2',
            'key3' => 'value3',
            'key4' => 'value4'
        ];
        $this->assertSame($expected, RP::parse($raw));
    }

    public function testParseResponseWithDuplicateKeys(): void
    {
        $raw = "key1=value1\nkey1=value2\nkey2=value3";
        $expected = [
            'key1' => 'value2', // The last occurrence is stored
            'key2' => 'value3'
        ];
        $this->assertSame($expected, RP::parse($raw));
    }

    public function testParseResponseWithSingleValidLine(): void
    {
        $raw = "key1=value1";
        $expected = [
            'key1' => 'value1'
        ];
        $this->assertSame($expected, RP::parse($raw));
    }

    public function testParseResponseWithOnlyEqualsSign(): void
    {
        $raw = "=";
        $expected = ['' => '']; // Matches parser behavior
        $this->assertSame($expected, RP::parse($raw));
    }

    public function testParseResponseWithMixedValidAndInvalidLines(): void
    {
        $raw = "validKey=validValue\ninvalidLine=\nanotherValid=anotherValue";
        $expected = [
            'validKey' => 'validValue',
            'invalidLine' => '', // Parser treats it as an empty value
            'anotherValid' => 'anotherValue'
        ];
        $this->assertSame($expected, RP::parse($raw));
    }
}
