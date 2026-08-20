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

    /**
     * The generic Domain/Create failure shape — code and message at the top
     * level. Pins the first half of getCode()/getDescription(): remove the
     * top-level lookup and this fails while the product[0] test below passes.
     *
     * Fixture is a faithful JSON translation of the plain-text capture in
     * RTLDEV-16781, not a verbatim one — that ticket predates the
     * ResponseFormat=JSON switch it prompted.
     */
    public function testJsonErrorResponsePreFlightShapeReadsCodeAndMessageFromTopLevel(): void
    {
        $cmd = ["ResponseFormat" => "JSON"];
        $json = (string) json_encode([
            "transactid" => "4d91116180d6d3f2f7d739613a8ac692",
            "status" => "FAILURE",
            "message" => "Domain \"ibswhmcstestdomain279.com\" is not available for registration",
            "code" => 100017,
        ]);
        $r = new R($json, $cmd);

        $this->assertTrue($r->isError(), "top-level status=FAILURE must be reported as an error");
        $this->assertSame(100017, $r->getCode());
        $this->assertSame(
            "Domain \"ibswhmcstestdomain279.com\" is not available for registration",
            $r->getDescription()
        );
    }

    /**
     * The provisioning Domain/Create failure shape — top-level status, but code
     * and message only under product[0]. This is the shape the
     * getCode()/getDescription() fallback exists for, so it pins the second half:
     * remove the product[0] lookup and this fails while the pre-flight test above
     * passes. Both must stay.
     *
     * The fallback is **not transitional**. It compensates for a genuine API
     * defect recorded in RTLDEV-16781, which is still To Do and unassigned, so
     * the compensation is indefinite — do not read it as scaffolding awaiting
     * removal (RSRMID-2972).
     *
     * Same caveat as above: a faithful JSON translation of a plain-text capture,
     * where the flat product_0_* keys nest into a product list. Provoking this
     * shape against OT&E is not reliably reproducible — it is a provisioning
     * failure, not a pre-flight one, so Domain/Create against an already-taken
     * domain yields the pre-flight shape instead. The capture is the source of
     * truth; do not try to reproduce it live.
     *
     * Known limitation, deliberately unhandled: only product[0] is read. IBS has
     * no batch-create endpoint, so there is never a second product. Revisit by
     * patch if one appears.
     */
    public function testJsonErrorResponseProvisioningShapeReadsCodeAndMessageFromFirstProduct(): void
    {
        $cmd = ["ResponseFormat" => "JSON"];
        $json = (string) json_encode([
            "transactid" => "5b555572ed58ad1eae44e8c774b8498c",
            "status" => "FAILURE",
            "product" => [
                [
                    "status" => "FAILURE",
                    "message" => "Failed to provide \"ibswhmcstestdomain312.com\"!",
                    "code" => 100049,
                ],
            ],
        ]);
        $r = new R($json, $cmd);

        // isError() reads the root status only, which is correct here precisely
        // because the API always sends top-level status and omits only code and
        // message on a provisioning failure. See IBS\Response::isError().
        $this->assertTrue($r->isError(), "top-level status=FAILURE must be reported as an error");
        $this->assertSame(100049, $r->getCode(), "the code must come from product[0] when absent up top");
        $this->assertSame("Failed to provide \"ibswhmcstestdomain312.com\"!", $r->getDescription());
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

        // One column per top-level JSON key, except response metadata
        // (RSRMID-2965): "transactid" and "status" are never registered as
        // columns, so 10 keys yield 8 columns.
        $this->assertCount(8, $r->getColumns());
        $colKeys = $r->getColumnKeys();
        $this->assertContains("domain", $colKeys);
        $this->assertContains("nameserver", $colKeys);
        $this->assertContains("contacts", $colKeys);

        // Two records: nameserver is the longest column (length 2)
        $this->assertCount(2, $r->getRecords());
    }
}
