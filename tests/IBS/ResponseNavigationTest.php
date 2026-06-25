<?php

declare(strict_types=1);

namespace CNICTEST\IBS;

use CNIC\IBS\Response as R;
use PHPUnit\Framework\TestCase;

/**
 * Coverage for IBS\Response record navigation, pagination metadata,
 * column access and the response code/description fallbacks.
 *
 * All fixtures are template-driven (no live API / credentials required).
 */
final class ResponseNavigationTest extends TestCase
{
    /** @var array<string,string> command forcing JSON parsing */
    private const JSONCMD = ["ResponseFormat" => "JSON"];

    /**
     * A three-record list response mirroring Domain/List: the "domain" list
     * column drives the record count, "domaincount" is the count/metadata
     * column stripped by getColumnKeys(true).
     */
    private function listResponse(): R
    {
        $json = '{"status":"SUCCESS","domaincount":3,"domain":["a.com","b.com","c.com"]}';
        return new R($json, self::JSONCMD);
    }

    // --- record navigation cursor ---

    public function testRecordCursorWalksForwardAndStopsAtEnd(): void
    {
        $r = $this->listResponse();
        $this->assertEquals(3, $r->getRecordsCount());

        $cur = $r->getCurrentRecord();
        $this->assertNotNull($cur);
        $this->assertEquals("a.com", $cur->getDataByKey("domain"));

        $next = $r->getNextRecord();
        $this->assertNotNull($next);
        $this->assertEquals("b.com", $next->getDataByKey("domain"));

        $next = $r->getNextRecord();
        $this->assertNotNull($next);
        $this->assertEquals("c.com", $next->getDataByKey("domain"));

        // already at the last record -> no further record
        $this->assertNull($r->getNextRecord());
    }

    public function testRecordCursorWalksBackwardAndRewinds(): void
    {
        $r = $this->listResponse();
        $r->getNextRecord(); // -> index 1
        $r->getNextRecord(); // -> index 2

        $prev = $r->getPreviousRecord();
        $this->assertNotNull($prev);
        $this->assertEquals("b.com", $prev->getDataByKey("domain"));

        // rewind returns $this (fluent) and resets to the first record
        $first = $r->rewindRecordList()->getCurrentRecord();
        $this->assertNotNull($first);
        $this->assertEquals("a.com", $first->getDataByKey("domain"));

        // at index 0 there is no previous record
        $this->assertNull($r->getPreviousRecord());
    }

    public function testGetRecordByIndexBounds(): void
    {
        $r = $this->listResponse();

        $rec = $r->getRecord(2);
        $this->assertNotNull($rec);
        $this->assertEquals("c.com", $rec->getDataByKey("domain"));

        $this->assertNull($r->getRecord(-1));
        $this->assertNull($r->getRecord(3));
    }

    public function testStatusOnlyResponseHasSingleRecord(): void
    {
        // a status-only response has exactly one column -> exactly one record
        $r = new R('{"status":"SUCCESS"}', self::JSONCMD);
        $this->assertEquals(1, $r->getRecordsCount());
        $this->assertNotNull($r->getCurrentRecord());
    }

    // --- pagination metadata ---

    public function testPaginationMetadata(): void
    {
        $r = $this->listResponse();
        $pg = $r->getPagination();

        $this->assertEquals(3, $pg["COUNT"]);
        $this->assertEquals(1, $pg["CURRENTPAGE"]);
        $this->assertEquals(0, $pg["FIRST"]);
        $this->assertEquals(2, $pg["LAST"]); // recordsCount(3) - 1
        $this->assertEquals(3, $pg["LIMIT"]);
        $this->assertEquals(1, $pg["PAGES"]);
        $this->assertEquals(1, $pg["NEXTPAGE"]); // clamped to the last page
        $this->assertNull($pg["PREVIOUSPAGE"]); // already on the first page
        $this->assertEquals(3, $pg["TOTAL"]);
    }

    public function testPageNavigationHelpers(): void
    {
        $r = $this->listResponse();
        $this->assertEquals(1, $r->getCurrentPageNumber());
        $this->assertEquals(0, $r->getFirstRecordIndex());
        $this->assertEquals(1, $r->getNumberOfPages());
        $this->assertFalse($r->hasNextPage());
        $this->assertFalse($r->hasPreviousPage());
    }

    // --- getLastRecordIndex (regression for the cross-instance static leak) ---

    public function testGetLastRecordIndexIsComputedPerInstance(): void
    {
        // Two independent responses with different record counts. Before the fix
        // a method-scoped `static $last` cached the first result and poisoned the
        // second (returning null). Each must now report its own count - 1.
        $a = new R('{"status":"SUCCESS","item":["a","b","c","d","e"]}', self::JSONCMD); // 5 rows
        $b = new R('{"status":"SUCCESS","item":["x","y"]}', self::JSONCMD);             // 2 rows

        $this->assertEquals(4, $a->getLastRecordIndex()); // 5 - 1
        $this->assertEquals(1, $b->getLastRecordIndex()); // 2 - 1
        // re-reading A must remain stable and independent of B
        $this->assertEquals(4, $a->getLastRecordIndex());
    }

    public function testGetLastRecordIndexFallsBackToCountWithoutCountColumn(): void
    {
        // No pagination/count column: fall back to the single-page model and
        // report count - 1 (mirrors CNR\Response), so FIRST/LAST form a coherent
        // pair instead of FIRST=0 / LAST=null. Here the response holds a single
        // record, so the last index is 0.
        $r = new R('{"status":"SUCCESS","domain":["a.com"]}', self::JSONCMD);
        $this->assertEquals(0, $r->getLastRecordIndex());
    }

    // --- column access ---

    public function testGetColumnIndex(): void
    {
        $r = $this->listResponse();
        $this->assertEquals("b.com", $r->getColumnIndex("domain", 1));
        $this->assertNull($r->getColumnIndex("domain", 9));
        $this->assertNull($r->getColumnIndex("doesnotexist", 0));
    }

    public function testGetColumnKeysFiltersPaginationColumns(): void
    {
        $r = $this->listResponse();
        $this->assertEquals(["status", "domaincount", "domain"], $r->getColumnKeys());
        // the "domaincount" count/metadata column is stripped when filtering
        $this->assertEquals(["status", "domain"], $r->getColumnKeys(true));
    }

    /**
     * Lock in the anchored pagination-key regex against the real IBS column
     * shapes. The count/metadata keys emitted by the list endpoints
     * (domaincount, total_rules, total_records) must be stripped, while
     * genuine data columns that merely contain those substrings must not be
     * — including "totaldomains" (Domain/Count's aggregate sum) and a TLD key
     * literally named "discount" (".discount" is a real gTLD in Domain/Count).
     */
    public function testGetColumnKeysFilteringMatchesRealKeyShapes(): void
    {
        // stripped: the documented count/metadata keys
        foreach (["domaincount", "total_rules", "total_records"] as $key) {
            $r = new R('{"status":"SUCCESS","' . $key . '":2,"domain":["a.com","b.com"]}', self::JSONCMD);
            $this->assertNotContains($key, $r->getColumnKeys(true), "$key should be stripped");
        }

        // kept: aggregate data and substring look-alikes must survive filtering
        $r = new R('{"status":"SUCCESS","com":2655,"discount":4,"totaldomains":4142}', self::JSONCMD);
        $kept = $r->getColumnKeys(true);
        foreach (["status", "com", "discount", "totaldomains"] as $key) {
            $this->assertContains($key, $kept, "$key must not be stripped");
        }
    }

    public function testGetCommandAndPlain(): void
    {
        $r = $this->listResponse();
        $cmd = $r->getCommand();
        $this->assertNotEmpty($cmd);
        $this->assertStringContainsString("JSON", $r->getCommandPlain());
    }

    // --- code / description fallbacks ---

    public function testGetCodeDefaultsTo200(): void
    {
        $r = new R('{"status":"SUCCESS","domain":"x.com"}', self::JSONCMD);
        $this->assertEquals(200, $r->getCode());
    }

    public function testGetCodeFallsBackToProductCode(): void
    {
        $r = new R('{"status":"SUCCESS","product_0_code":"201"}', self::JSONCMD);
        $this->assertEquals(201, $r->getCode());
    }

    public function testGetDescriptionDefault(): void
    {
        $r = new R('{"status":"SUCCESS","domain":"x.com"}', self::JSONCMD);
        $this->assertEquals("Command completed successfully", $r->getDescription());
    }

    public function testGetDescriptionFallsBackToProductMessage(): void
    {
        $r = new R('{"status":"SUCCESS","product_0_message":"Created"}', self::JSONCMD);
        $this->assertEquals("Created", $r->getDescription());
    }

    // --- not-supported / not-implemented contract methods ---

    public function testGetQueuetimeThrows(): void
    {
        $r = new R('{"status":"SUCCESS"}', self::JSONCMD);
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("Not supported");
        $r->getQueuetime();
    }

    public function testGetRuntimeThrows(): void
    {
        $r = new R('{"status":"SUCCESS"}', self::JSONCMD);
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("Not supported");
        $r->getRuntime();
    }

    public function testIsTmpErrorThrows(): void
    {
        $r = new R('{"status":"SUCCESS"}', self::JSONCMD);
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("Not supported");
        $r->isTmpError();
    }

    public function testIsPendingThrows(): void
    {
        $r = new R('{"status":"SUCCESS"}', self::JSONCMD);
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("Not supported");
        $r->isPending();
    }

    public function testGetListHashThrows(): void
    {
        $r = new R('{"status":"SUCCESS"}', self::JSONCMD);
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("Not implemented.");
        $r->getListHash();
    }
}
