<?php

declare(strict_types=1);

namespace CNICTEST\IBS;

use CNIC\IBS\Response as R;
use PHPUnit\Framework\TestCase;

/**
 * Response-level behaviour for IBS. The parser has its own direct tests in
 * ResponseParserTest.php (RSRMID-2924) — do not route parse assertions through
 * a constructed Response again.
 */
final class ResponseTest extends TestCase
{
    public function testCommandSecureMasksSensitiveFields(): void
    {
        // password and the transfer auth code are sensitive; masking is case-insensitive
        $r = new R("{}", [
            "command" => "RegisterDomain",
            "password" => "secret",
            "TransferAuthInfo" => "qCg+ic'G1m"
        ]);
        $cmd = $r->getCommand();
        $this->assertEquals("***", $cmd["password"]);
        $this->assertEquals("***", $cmd["TransferAuthInfo"]);
    }

    // --- construction and error templates ---

    public function testConstructorEmptyResponse(): void
    {
        $r = new R("");
        $this->assertEquals("FAILURE", $r->getHash()["status"] ?? null);
        $this->assertEquals("423 Empty API response. Probably unreachable API end point", $r->getDescription());
    }

    public function testHttpErrorTemplate(): void
    {
        // The transport error travels as the declared $error parameter now,
        // not encoded into $raw (RSRMID-2937) — $raw is unusable and ignored.
        $r = new R("", error: "Connection timed out");
        $this->assertTrue($r->isError());
        $this->assertEquals("FAILURE", $r->getHash()["status"] ?? null);
        $this->assertStringContainsString("Connection timed out", $r->getDescription());
    }

    public function testTemplateLookupByRawId(): void
    {
        // A raw payload equal to a known template id is the sanctioned
        // mocking route (see AbstractResponseTranslator::resolveTemplateId()),
        // not a leak. "notfound" is a built-in, so it resolves against the
        // brand default registry with nothing to register.
        $r = new R("notfound");
        $this->assertTrue($r->isError());
        $this->assertEquals("FAILURE", $r->getHash()["status"] ?? null);
        $this->assertStringContainsString("Response Template not found", $r->getDescription());
    }

    public function testEmptyResponseWithJsonCommand(): void
    {
        $cmd = ["ResponseFormat" => "JSON"];
        $r = new R("", $cmd);
        $this->assertTrue($r->isError());
        $this->assertEquals("FAILURE", $r->getHash()["status"] ?? null);
        $this->assertStringContainsString("Empty API response", $r->getDescription());
    }

    // --- JSON responses (with ResponseFormat=JSON command) ---

    public function testJsonSuccessResponse(): void
    {
        $cmd = ["ResponseFormat" => "JSON"];
        $json = '{"transactid":"xyz789","status":"SUCCESS","domain":"ibstest.com","expirationdate":"2026/02/20"}';
        $r = new R($json, $cmd);
        $this->assertTrue($r->isSuccess());
        $this->assertEquals("SUCCESS", $r->getHash()["status"] ?? null);
        $this->assertEquals("ibstest.com", $r->getHash()["domain"]);
        $this->assertEquals("2026/02/20", $r->getHash()["expirationdate"]);
    }

    public function testJsonErrorResponse(): void
    {
        $cmd = ["ResponseFormat" => "JSON"];
        $json = '{"transactid":"abc123","status":"FAILURE","message":"Permission denied! \"available123test.com\" permission is not granted.","code":100005}';
        $r = new R($json, $cmd);
        $this->assertTrue($r->isError());
        $this->assertEquals("FAILURE", $r->getHash()["status"] ?? null);
        $this->assertStringContainsString("Permission denied!", $r->getDescription());
        $this->assertEquals(100005, $r->getCode());
    }

    public function testJsonDomainInfoResponse(): void
    {
        $cmd = ["ResponseFormat" => "JSON"];
        $data = [
            "transactid" => "8986680508b740347a73e339b5c3bd67",
            "status" => "SUCCESS",
            "domain" => "ibstest.com",
            "expirationdate" => "2026/02/20",
            "registrationdate" => "2025/02/20",
            "paiduntil" => "2026/02/20",
            "domainstatus" => "EXPIRED",
            "contacts" => [
                "registrant" => ["firstname" => "Middle", "lastname" => "Ware"],
                "admin" => ["firstname" => "Kai", "lastname" => "Schwarz"]
            ],
            "nameserver" => ["ns1.ispapi.net", "ns2.ispapi.net"],
            "transferauthinfo" => "qCg+ic'G1m"
        ];
        $json = (string) json_encode($data);
        $r = new R($json, $cmd);
        $this->assertTrue($r->isSuccess());
        $this->assertEquals("ibstest.com", $r->getHash()["domain"]);
        $this->assertEquals("2026/02/20", $r->getHash()["expirationdate"]);
        $this->assertEquals("2025/02/20", $r->getHash()["registrationdate"]);
        $this->assertEquals("2026/02/20", $r->getHash()["paiduntil"]);
        // nested objects and arrays preserved
        $this->assertIsArray($r->getHash()["contacts"]);
        $nameserver = $r->getHash()["nameserver"];
        $this->assertIsArray($nameserver);
        $this->assertEquals([
            "registrant" => ["firstname" => "Middle", "lastname" => "Ware"],
            "admin"      => ["firstname" => "Kai",    "lastname" => "Schwarz"],
        ], $r->getHash()["contacts"]);
        $this->assertEquals("ns1.ispapi.net", $nameserver[0]);

        // One column per top-level JSON key: 10 total
        $this->assertCount(10, $r->getColumns());
        $colKeys = $r->getColumnKeys();
        $this->assertContains("domain", $colKeys);
        $this->assertContains("nameserver", $colKeys);
        $this->assertContains("contacts", $colKeys);

        // Two records: nameserver is the longest column (length 2)
        $this->assertCount(2, $r->getRecords());
    }
}
