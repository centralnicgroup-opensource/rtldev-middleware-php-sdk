<?php

declare(strict_types=1);

namespace CNICTEST;

use CNIC\ApiDateTime;
use CNIC\Record;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Shared record behaviour, covered once for every brand.
 *
 * Record data has one shape across brands (array<string,mixed>), so there is a
 * single CNIC\Record that every Response's newRecord() hook returns; these
 * assertions are the only place its contract needs covering. Brand test classes
 * cover the per-brand Response wiring instead.
 */
final class RecordTest extends TestCase
{
    private const array ROW = [
        "DOMAIN" => "mydomain.com",
        "RATING" => "1",
        "RNDINT" => "321",
        "SUM"    => "1",
    ];

    public function testGetData(): void
    {
        $rec = new Record(self::ROW);
        $this->assertSame(self::ROW, $rec->getData());
    }

    public function testGetDataOfEmptyRow(): void
    {
        $rec = new Record([]);
        $this->assertSame([], $rec->getData());
    }

    public function testGetDataByKeyFound(): void
    {
        $rec = new Record(self::ROW);
        $this->assertSame("mydomain.com", $rec->getDataByKey("DOMAIN"));
        $this->assertSame("321", $rec->getDataByKey("RNDINT"));
    }

    public function testGetDataByKeyNotFound(): void
    {
        $rec = new Record(self::ROW);
        $this->assertNull($rec->getDataByKey("KEYNOTEXISTING"));
    }

    public function testGetDataByKeyReturnsNestedArray(): void
    {
        $contacts = [
            "registrant" => ["firstname" => "Middle", "lastname" => "Ware"],
            "admin"      => ["firstname" => "Kai",    "lastname" => "Schwarz"],
        ];
        $rec = new Record(["contacts" => $contacts]);
        $this->assertSame($contacts, $rec->getDataByKey("contacts"));
    }

    /**
     * A key present with a null value must be distinguishable from an absent
     * key only by getData() — getDataByKey() returns null for both, which is
     * the documented contract (array_key_exists, not isset, is what runs).
     */
    public function testGetDataByKeyOfNullValue(): void
    {
        $rec = new Record(["EMPTY" => null]);
        $this->assertNull($rec->getDataByKey("EMPTY"));
        $this->assertArrayHasKey("EMPTY", $rec->getData());
    }

    // --- getDateTimeByKey() ---

    public function testGetDateTimeByKeyParsesDashSeparatedValue(): void
    {
        $rec = new Record(["EXPIRATIONDATE" => "2026-07-25 07:46:34"]);
        $dt = $rec->getDateTimeByKey("EXPIRATIONDATE");
        $this->assertInstanceOf(ApiDateTime::class, $dt);
        $this->assertSame(1784965594, $dt->ts);
    }

    public function testGetDateTimeByKeyParsesSlashSeparatedValue(): void
    {
        $rec = new Record(["EXPIRATIONDATE" => "2030/07/17"]);
        $dt = $rec->getDateTimeByKey("EXPIRATIONDATE");
        $this->assertInstanceOf(ApiDateTime::class, $dt);
        $this->assertSame("2030-07-17", $dt->date);
    }

    public function testGetDateTimeByKeyOfDateOnlyValueHasNullTs(): void
    {
        $rec = new Record(["EXPIRATIONDATE" => "2030/07/17"]);
        $dt = $rec->getDateTimeByKey("EXPIRATIONDATE");
        $this->assertInstanceOf(ApiDateTime::class, $dt);
        $this->assertNull($dt->ts);
    }

    public function testGetDateTimeByKeyOfMissingKeyIsNull(): void
    {
        $rec = new Record(self::ROW);
        $this->assertNull($rec->getDateTimeByKey("NOTAKEY"));
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
    public function testGetDateTimeByKeyOfNonStringValueIsNull(mixed $value): void
    {
        $rec = new Record(["EXPIRATIONDATE" => $value]);
        $this->assertNull($rec->getDateTimeByKey("EXPIRATIONDATE"));
    }

    public function testGetDateTimeByKeyOfUnparsableStringIsNull(): void
    {
        $rec = new Record(["EXPIRATIONDATE" => "not a date"]);
        $this->assertNull($rec->getDateTimeByKey("EXPIRATIONDATE"));
    }
}
