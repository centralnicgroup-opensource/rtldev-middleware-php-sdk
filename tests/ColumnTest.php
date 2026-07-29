<?php

declare(strict_types=1);

namespace CNICTEST;

use CNIC\Column;
use PHPUnit\Framework\TestCase;

/**
 * Shared column behaviour, covered once for every brand.
 *
 * CNIC\Column is what IBS/Moniker responses instantiate directly and what
 * CNR\Column inherits, so these assertions are the single source of coverage
 * for the key/data/length/bounds contract. Brand test classes only cover what
 * is genuinely brand-specific (CNR's narrowed return type, the per-brand
 * Response wiring).
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
        // bound explicitly: an empty literal would otherwise infer TValue as
        // never, which makes the out-of-bounds null a static certainty rather
        // than the runtime behaviour under test
        /** @var Column<string> $col */
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
}
