<?php

declare(strict_types=1);

namespace CNICTEST\IBS;

use CNIC\Column;
use CNIC\IBS\Response as R;
use PHPUnit\Framework\TestCase;

/**
 * IBS-specific column behaviour.
 *
 * The key/data/length/bounds contract — including the mixed-typed and nested
 * JSON values IBS relies on — is shared and covered once in CNICTEST\ColumnTest
 * against CNIC\Column, which IBS instantiates directly. What is left here is the
 * wiring: how a real IBS JSON response turns into columns.
 */
final class ColumnTest extends TestCase
{
    // Domain info fixture — mirrors ResponseTest::testJsonDomainInfoResponse
    private const array NAMESERVERS = ["ns1.ispapi.net", "ns2.ispapi.net"];
    private const array FULL_CONTACTS = [
        "registrant" => ["firstname" => "Middle", "lastname" => "Ware"],
        "admin"      => ["firstname" => "Kai",    "lastname" => "Schwarz"],
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
            "contacts"         => self::FULL_CONTACTS,
            "nameserver"       => self::NAMESERVERS,
            "transferauthinfo" => "qCg+ic'G1m",
        ];
        return new R((string) json_encode($data), $cmd);
    }

    /**
     * Guards the addColumn() wiring: IBS uses the shared column type as-is,
     * since its JSON values are arbitrary and need no narrowing.
     */
    public function testResponseBuildsSharedColumnType(): void
    {
        $col = $this->buildDomainInfoResponse()->getColumn("nameserver");
        $this->assertInstanceOf(Column::class, $col);
    }

    public function testNameserverColumnFromResponse(): void
    {
        $col = $this->buildDomainInfoResponse()->getColumn("nameserver");
        $this->assertNotNull($col);
        $this->assertSame(self::NAMESERVERS, $col->getData());
        $this->assertSame("ns1.ispapi.net", $col->getDataByIndex(0));
        $this->assertSame("ns2.ispapi.net", $col->getDataByIndex(1));
        $this->assertNull($col->getDataByIndex(2));
    }

    public function testContactsColumnFromResponse(): void
    {
        $col = $this->buildDomainInfoResponse()->getColumn("contacts");
        $this->assertNotNull($col);
        // associative object → stored as one column entry preserving registrant/admin keys
        $this->assertCount(1, $col->getData());
        // full structure equality also covers the nested registrant/admin values
        $this->assertSame(self::FULL_CONTACTS, $col->getDataByIndex(0));
        $this->assertNull($col->getDataByIndex(1));
    }
}
