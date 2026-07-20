<?php

declare(strict_types=1);

namespace CNICTEST\IBS;

use CNIC\IBS\Record;
use CNIC\IBS\Response as R;
use PHPUnit\Framework\TestCase;

final class RecordTest extends TestCase
{
    // Mirrors record 0 built by IBS\Response from the domain info fixture.
    // contacts is the full associative object — preserved as one column entry.
    private const array DOMAIN_INFO_RECORD = [
        "transactid"       => "8986680508b740347a73e339b5c3bd67",
        "status"           => "SUCCESS",
        "domain"           => "ibstest.com",
        "expirationdate"   => "2026-02-20",
        "registrationdate" => "2025-02-20",
        "paiduntil"        => "2026-02-20",
        "domainstatus"     => "EXPIRED",
        "contacts"         => [
            "registrant" => ["firstname" => "Middle", "lastname" => "Ware"],
            "admin"      => ["firstname" => "Kai",    "lastname" => "Schwarz"],
        ],
        "nameserver"       => "ns1.ispapi.net",
        "transferauthinfo" => "qCg+ic'G1m",
    ];

    private function buildDomainInfoResponse(): R
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
            "contacts"         => self::DOMAIN_INFO_RECORD["contacts"],
            "nameserver"       => ["ns1.ispapi.net", "ns2.ispapi.net"],
            "transferauthinfo" => "qCg+ic'G1m",
        ];
        return new R((string) json_encode($data), $cmd);
    }

    // --- Unit tests: Record class directly ---

    public function testGetData(): void
    {
        $rec = new Record(self::DOMAIN_INFO_RECORD);
        $this->assertSame(self::DOMAIN_INFO_RECORD, $rec->getData());
    }

    public function testGetDataByKeyFound(): void
    {
        $rec = new Record(self::DOMAIN_INFO_RECORD);
        $this->assertSame("ibstest.com", $rec->getDataByKey("domain"));
        $this->assertSame("EXPIRED", $rec->getDataByKey("domainstatus"));
        $this->assertSame("2026-02-20", $rec->getDataByKey("expirationdate"));
        $this->assertSame("ns1.ispapi.net", $rec->getDataByKey("nameserver"));
    }

    public function testGetDataByKeyReturnsNestedArray(): void
    {
        $rec      = new Record(self::DOMAIN_INFO_RECORD);
        $contacts = $rec->getDataByKey("contacts");
        $this->assertSame([
            "registrant" => ["firstname" => "Middle", "lastname" => "Ware"],
            "admin"      => ["firstname" => "Kai",    "lastname" => "Schwarz"],
        ], $contacts);
    }

    public function testGetDataByKeyNotFound(): void
    {
        $rec = new Record(self::DOMAIN_INFO_RECORD);
        $this->assertNull($rec->getDataByKey("nonexistent"));
    }

    // --- Integration tests: records extracted from a real Response ---

    public function testRecord0FromDomainInfoResponse(): void
    {
        $rec = $this->buildDomainInfoResponse()->getRecord(0);
        $this->assertNotNull($rec);
        // IBS\Response must build IBS\Record (not the CNR base) — guards the
        // newRecord() factory-hook wiring so IBS-specific record behaviour runs.
        $this->assertInstanceOf(Record::class, $rec);
        // all scalar fields present at index 0
        $this->assertSame("ibstest.com", $rec->getDataByKey("domain"));
        $this->assertSame("2026-02-20", $rec->getDataByKey("expirationdate"));
        $this->assertSame("EXPIRED", $rec->getDataByKey("domainstatus"));
        $this->assertSame("ns1.ispapi.net", $rec->getDataByKey("nameserver"));
        // contacts full object preserved (nested registrant/admin values covered by equality)
        $this->assertSame(self::DOMAIN_INFO_RECORD["contacts"], $rec->getDataByKey("contacts"));
    }

    public function testRecord1FromDomainInfoResponse(): void
    {
        $rec = $this->buildDomainInfoResponse()->getRecord(1);
        $this->assertNotNull($rec);
        // only nameserver[1] — contacts has no second entry, scalar columns have no second row
        $this->assertCount(1, $rec->getData());
        $this->assertSame("ns2.ispapi.net", $rec->getDataByKey("nameserver"));
        $this->assertNull($rec->getDataByKey("contacts"));
        $this->assertNull($rec->getDataByKey("domain"));
    }
}
