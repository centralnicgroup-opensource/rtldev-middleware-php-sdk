<?php

declare(strict_types=1);

namespace CNICTEST\IBS;

use CNIC\IBS\Column;
use CNIC\IBS\Response as R;
use PHPUnit\Framework\TestCase;

final class ColumnTest extends TestCase
{
    // Domain info fixture — mirrors ResponseTest::testJsonDomainInfoResponse
    private const NAMESERVERS = ["ns1.ispapi.net", "ns2.ispapi.net"];
    private const CONTACTS = [
        ["firstname" => "Middle", "lastname" => "Ware"],
        ["firstname" => "Kai",    "lastname" => "Schwarz"],
    ];
    private const FULL_CONTACTS = [
        "registrant" => ["firstname" => "Middle", "lastname" => "Ware"],
        "admin"      => ["firstname" => "Kai",    "lastname" => "Schwarz"],
    ];

    private static function buildDomainInfoResponse(): R
    {
        $cmd  = ["ResponseFormat" => "JSON"];
        $data = [
            "transactid"       => "8986680508b740347a73e339b5c3bd67",
            "status"           => "SUCCESS",
            "domain"           => "ibstest.com",
            "expirationdate"   => "2026/02/20",
            "registrationdate" => "2025/02/20",
            "paiduntil"        => "2026/02/20",
            "domainstatus"     => "EXPIRED",
            "contacts"         => self::FULL_CONTACTS,
            "nameserver"       => self::NAMESERVERS,
            "transferauthinfo" => "qCg+ic'G1m",
        ];
        return new R(json_encode($data) ?: "", $cmd);
    }

    // --- Unit tests: Column class directly ---

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
        $col = new Column("contacts", self::CONTACTS);
        $this->assertSame(self::CONTACTS, $col->getData());
        $this->assertSame(self::CONTACTS[0], $col->getDataByIndex(0));
        $this->assertSame("Middle", $col->getDataByIndex(0)["firstname"]);
        $this->assertSame(self::CONTACTS[1], $col->getDataByIndex(1));
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

    // --- Integration tests: columns extracted from a real Response ---

    public function testNameserverColumnFromResponse(): void
    {
        $col = self::buildDomainInfoResponse()->getColumn("nameserver");
        $this->assertNotNull($col);
        $this->assertSame(self::NAMESERVERS, $col->getData());
        $this->assertSame("ns1.ispapi.net", $col->getDataByIndex(0));
        $this->assertSame("ns2.ispapi.net", $col->getDataByIndex(1));
        $this->assertNull($col->getDataByIndex(2));
    }

    public function testContactsColumnFromResponse(): void
    {
        $col = self::buildDomainInfoResponse()->getColumn("contacts");
        $this->assertNotNull($col);
        // associative object → stored as one column entry preserving registrant/admin keys
        $this->assertCount(1, $col->getData());
        $this->assertSame(self::FULL_CONTACTS, $col->getDataByIndex(0));
        $this->assertSame("Middle", $col->getDataByIndex(0)["registrant"]["firstname"]);
        $this->assertNull($col->getDataByIndex(1));
    }
}
