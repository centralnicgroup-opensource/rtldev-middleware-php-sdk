<?php

declare(strict_types=1);

namespace CNICTEST;

use CNIC\CommandFormatter as CF;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for CNIC\CommandFormatter.
 *
 * CommandFormatter serialises every outbound API command, so its flattening,
 * priority sorting and plain-text formatting are exercised directly here rather
 * than only indirectly through the client request flow.
 */
final class CommandFormatterTest extends TestCase
{
    public function testFlattenUppercasesKeysByDefault(): void
    {
        $this->assertSame(
            ["COMMAND" => "StatusAccount"],
            CF::flattenCommand(["command" => "StatusAccount"])
        );
    }

    public function testFlattenPreservesKeyCaseWhenToupperIsFalse(): void
    {
        $this->assertSame(
            ["ResponseFormat" => "JSON"],
            CF::flattenCommand(["ResponseFormat" => "JSON"], false)
        );
    }

    public function testFlattenSkipsNullValues(): void
    {
        $out = CF::flattenCommand(["A" => null, "B" => "keep"]);
        $this->assertArrayNotHasKey("A", $out);
        $this->assertSame(["B" => "keep"], $out);
    }

    public function testFlattenExplodesNestedArraysIntoIndexedKeys(): void
    {
        $out = CF::flattenCommand(["NAMESERVER" => ["ns1.example.com", "ns2.example.com"]]);
        $this->assertSame(
            [
                "NAMESERVER0" => "ns1.example.com",
                "NAMESERVER1" => "ns2.example.com",
            ],
            $out
        );
    }

    public function testFlattenStripsCarriageReturnsAndNewlines(): void
    {
        $out = CF::flattenCommand(["COMMAND" => "Line1\r\nLine2\nLine3"]);
        $this->assertSame(["COMMAND" => "Line1Line2Line3"], $out);
    }

    public function testFlattenStripsNewlinesInsideNestedArrayValues(): void
    {
        $out = CF::flattenCommand(["X-DATA" => ["a\nb", "c\r\nd"]]);
        $this->assertSame(
            [
                "X-DATA0" => "ab",
                "X-DATA1" => "cd",
            ],
            $out
        );
    }

    public function testFlattenCoercesScalarsToString(): void
    {
        $out = CF::flattenCommand([
            "INT" => 42,
            "FLOAT" => 1.5,
            "BOOLTRUE" => true,
            "BOOLFALSE" => false,
            "ZERO" => 0,
        ]);
        $this->assertSame("42", $out["INT"]);
        $this->assertSame("1.5", $out["FLOAT"]);
        $this->assertSame("1", $out["BOOLTRUE"]);
        $this->assertSame("", $out["BOOLFALSE"]);
        $this->assertSame("0", $out["ZERO"]);
    }

    public function testGetSortedCommandRanksCommandFirstThenProperties(): void
    {
        $sorted = CF::getSortedCommand([
            "ZZZ" => "z",
            "AAA" => "a",
            "DOMAIN" => "example.com",
            "COMMAND" => "AddDomain",
        ]);
        // COMMAND (1) then DOMAIN (2) then the unknown keys alphabetically.
        $this->assertSame(["COMMAND", "DOMAIN", "AAA", "ZZZ"], array_keys($sorted));
    }

    public function testGetSortedCommandOrdersContactFieldsByTypeThenField(): void
    {
        $sorted = CF::getSortedCommand([
            "OWNERCONTACT0LASTNAME" => "Doe",
            "OWNERCONTACT0FIRSTNAME" => "John",
            "COMMAND" => "AddDomain",
        ]);
        $this->assertSame(
            ["COMMAND", "OWNERCONTACT0FIRSTNAME", "OWNERCONTACT0LASTNAME"],
            array_keys($sorted)
        );
    }

    public function testGetSortedCommandFallsBackToAlphabeticalForUnknownKeys(): void
    {
        $sorted = CF::getSortedCommand(["GAMMA" => "3", "ALPHA" => "1", "BETA" => "2"]);
        $this->assertSame(["ALPHA", "BETA", "GAMMA"], array_keys($sorted));
    }

    public function testFormatCommandRendersKeyValueLines(): void
    {
        $this->assertSame(
            "COMMAND = StatusAccount\nDOMAIN = example.com\n",
            CF::formatCommand(["COMMAND" => "StatusAccount", "DOMAIN" => "example.com"])
        );
    }

    public function testFormatCommandReturnsEmptyStringForEmptyCommand(): void
    {
        $this->assertSame("", CF::formatCommand([]));
    }
}
