<?php

declare(strict_types=1);

namespace CNICTEST;

use CNIC\ApiDateTime;
use CNIC\Column;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Shared column behaviour, covered once for every brand.
 *
 * CNIC\Column is what every brand's Response instantiates directly — there is
 * no per-brand Column subclass — so these assertions are the single source of
 * coverage for the key/data/length/bounds contract, plus the getStringByIndex()/
 * getDateTimeByIndex() opt-in narrowing accessors declared on
 * {@see \CNIC\ColumnInterface}. Brand test classes only cover what is
 * genuinely brand-specific (the per-brand Response wiring).
 */
final class ColumnTest extends TestCase
{
    private const array NAMESERVERS = ["ns1.ispapi.net", "ns2.ispapi.net"];

    public function testGetKey(): void
    {
        $col = new Column("nameserver", self::NAMESERVERS);
        $this->assertSame("nameserver", $col->getKey());
    }

    public function testGetData(): void
    {
        $col = new Column("nameserver", self::NAMESERVERS);
        $this->assertSame(self::NAMESERVERS, $col->getData());
    }

    public function testLength(): void
    {
        $col = new Column("nameserver", self::NAMESERVERS);
        $this->assertSame(2, $col->length);
    }

    public function testLengthOfEmptyColumn(): void
    {
        $col = new Column("empty", []);
        $this->assertSame(0, $col->length);
        $this->assertSame([], $col->getData());
        $this->assertNull($col->getDataByIndex(0));
    }

    public function testGetDataByIndex(): void
    {
        $col = new Column("nameserver", self::NAMESERVERS);
        $this->assertSame("ns1.ispapi.net", $col->getDataByIndex(0));
        $this->assertSame("ns2.ispapi.net", $col->getDataByIndex(1));
        $this->assertNull($col->getDataByIndex(2));
    }

    public function testGetDataByIndexOutOfBounds(): void
    {
        $col = new Column("domain", ["ibstest.com"]);
        $this->assertNull($col->getDataByIndex(-1));
        $this->assertNull($col->getDataByIndex(1));
    }

    public function testNestedArrayDataPreserved(): void
    {
        $contacts = [
            ["firstname" => "Middle", "lastname" => "Ware"],
            ["firstname" => "Kai",    "lastname" => "Schwarz"],
        ];
        $col = new Column("contacts", $contacts);
        $this->assertSame($contacts, $col->getData());
        // per-index equality also covers the nested firstname/lastname values
        $this->assertSame($contacts[0], $col->getDataByIndex(0));
        $this->assertSame($contacts[1], $col->getDataByIndex(1));
        $this->assertNull($col->getDataByIndex(2));
    }

    public function testMixedScalarAndNestedData(): void
    {
        $mixed = ["scalar-value", ["nested" => "array"], "another-scalar"];
        $col   = new Column("mixed", $mixed);
        $this->assertSame($mixed, $col->getData());
        $this->assertSame("scalar-value", $col->getDataByIndex(0));
        $this->assertSame(["nested" => "array"], $col->getDataByIndex(1));
        $this->assertSame("another-scalar", $col->getDataByIndex(2));
    }

    // --- getStringByIndex() ---

    public function testGetStringByIndexOfStringValue(): void
    {
        $col = new Column("nameserver", self::NAMESERVERS);
        $this->assertSame("ns1.ispapi.net", $col->getStringByIndex(0));
    }

    public function testGetStringByIndexOfNonStringValueIsNull(): void
    {
        $col = new Column("contacts", [["firstname" => "Middle", "lastname" => "Ware"]]);
        $this->assertNull($col->getStringByIndex(0));
    }

    public function testGetStringByIndexOutOfRangeIsNull(): void
    {
        $col = new Column("nameserver", self::NAMESERVERS);
        $this->assertNull($col->getStringByIndex(2));
        $this->assertNull($col->getStringByIndex(-1));
    }

    // --- getDateTimeByIndex() ---

    public function testGetDateTimeByIndexParsesDashSeparatedValue(): void
    {
        $col = new Column("expirationdate", ["2026-07-25 07:46:34"]);
        $dt = $col->getDateTimeByIndex(0);
        $this->assertInstanceOf(ApiDateTime::class, $dt);
        $this->assertSame(1784965594, $dt->ts);
    }

    public function testGetDateTimeByIndexParsesSlashSeparatedValue(): void
    {
        $col = new Column("expirationdate", ["2030/07/17"]);
        $dt = $col->getDateTimeByIndex(0);
        $this->assertInstanceOf(ApiDateTime::class, $dt);
        $this->assertSame("2030-07-17", $dt->date);
    }

    public function testGetDateTimeByIndexOfDateOnlyValueHasNullTs(): void
    {
        $col = new Column("expirationdate", ["2030/07/17"]);
        $dt = $col->getDateTimeByIndex(0);
        $this->assertInstanceOf(ApiDateTime::class, $dt);
        $this->assertNull($dt->ts);
    }

    public function testGetDateTimeByIndexOutOfRangeIsNull(): void
    {
        $col = new Column("expirationdate", ["2030/07/17"]);
        $this->assertNull($col->getDateTimeByIndex(1));
        $this->assertNull($col->getDateTimeByIndex(-1));
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function nonStringValues(): array
    {
        return [
            "int" => [42],
            "array" => [["nested" => "value"]],
            "null" => [null],
            "bool" => [true],
        ];
    }

    #[DataProvider("nonStringValues")]
    public function testGetDateTimeByIndexOfNonStringValueIsNull(mixed $value): void
    {
        $col = new Column("expirationdate", [$value]);
        $this->assertNull($col->getDateTimeByIndex(0));
    }

    public function testGetDateTimeByIndexOfUnparsableStringIsNull(): void
    {
        $col = new Column("expirationdate", ["not a date"]);
        $this->assertNull($col->getDateTimeByIndex(0));
    }
}
