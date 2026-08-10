<?php

declare(strict_types=1);

//declare(strict_types=1);

namespace CNICTEST\CNR;

use CNIC\CNR\Response as R;
use CNIC\CNR\ResponseTemplateManager as RTM;
use PHPUnit\Framework\TestCase;

final class ResponseTest extends TestCase
{
    /**
     * @var string user name
     */
    public static string $user;
    /**
     * @var string password
     */
    public static string $pw;

    /**
     * This class' template registry. Instance state, so the templates below
     * reach only the responses explicitly built against it (RSRMID-2941) —
     * which is why there is no tearDownAfterClass() putting anything back.
     */
    public static RTM $tpls;

    #[\Override]
    public static function setUpBeforeClass(): void
    {
        self::$tpls = (new RTM())
            ->addTemplate("OK", "200", "Command completed successfully")
            ->addTemplate("listP0", "[RESPONSE]\r\nPROPERTY[TOTAL][0]=2701\r\nPROPERTY[FIRST][0]=0\r\nPROPERTY[DOMAIN][0]=0-60motorcycletimes.com\r\nPROPERTY[DOMAIN][1]=0-be-s01-0.com\r\nPROPERTY[COUNT][0]=2\r\nPROPERTY[LAST][0]=1\r\nPROPERTY[LIMIT][0]=2\r\nDESCRIPTION=Command completed successfully\r\nCODE=200\r\nQUEUETIME=0\r\nRUNTIME=0.023\r\nEOF\r\n");
        self::$user = (string) getenv("RTLDEV_MW_CI_USER_CNR");
        self::$pw = (string) getenv("RTLDEV_MW_CI_USERPASSWORD_CNR");
    }

    public function testCommandPlain(): void
    {
        // ensure no vars are returned in response, just in case no place holder replacements are provided
        $r = new R("", ["COMMAND" => "QueryDomainOptions", "DOMAIN0" => "example.com", "DOMAIN1" => "example.net"]);
        $expected = "COMMAND = QueryDomainOptions\nDOMAIN0 = example.com\nDOMAIN1 = example.net\n";
        $this->assertEquals($expected, $r->getCommandPlain());
    }

    public function testCommandPlainSecure(): void
    {
        // ensure no vars are returned in response, just in case no place holder replacements are provided
        $r = new R("", ["COMMAND" => "CheckAuthentication", "SUBUSER" => self::$user, "PASSWORD" => self::$pw]);
        $expected = "COMMAND = CheckAuthentication\nSUBUSER = " . self::$user . "\nPASSWORD = ***\n";
        $this->assertEquals($expected, $r->getCommandPlain());
    }

    public function testCommandPlainSecureMasksAuthCode(): void
    {
        // the domain authorization code (AUTH) is sensitive and must be masked too
        $r = new R("", ["COMMAND" => "TransferDomain", "DOMAIN" => "test.com", "AUTH" => "secretcode"]);
        $this->assertEquals("***", $r->getCommand()["AUTH"]);
    }

    public function testCommandPlainSecureCaseInsensitive(): void
    {
        // masking matches sensitive keys case-insensitively, regardless of the casing actually sent
        $r = new R("", ["COMMAND" => "CheckAuthentication", "password" => "secret", "Auth" => "secretcode"]);
        $cmd = $r->getCommand();
        $this->assertEquals("***", $cmd["password"]);
        $this->assertEquals("***", $cmd["Auth"]);
    }

    public function testGetContext(): void
    {
        $context = ["traceId" => "abc123", "attempt" => 1];
        $r = new R("OK", [], [], $context, templates: self::$tpls);
        $this->assertSame($context, $r->getContext());
    }

    public function testGetCurrentPageNumberEntries(): void
    {
        $r = new R("listP0", templates: self::$tpls);
        $this->assertEquals(1, $r->getCurrentPageNumber());
    }

    public function testGetCurrentPageNumberNoEntries(): void
    {
        $r = new R("OK", templates: self::$tpls);
        $this->assertNull($r->getCurrentPageNumber());
    }

    public function testGetFirstRecordIndexNoFirstNoRows(): void
    {
        $r = new R("OK", templates: self::$tpls);
        $this->assertNull($r->getFirstRecordIndex());
    }

    public function testGetFirstRecordIndexNoFirstRows(): void
    {
        $r = implode("\r\n", [
            "[RESPONSE]",
            "CODE=200",
            "DESCRIPTION=Command completed successfully",
            "PROPERTY[DOMAIN][0]=mydomain1.com",
            "PROPERTY[DOMAIN][1]=mydomain2.com",
            "EOF"
        ]);
        $r = new R($r);
        $this->assertEquals(0, $r->getFirstRecordIndex());
    }

    public function testGetColumns(): void
    {
        $r = new R("listP0", templates: self::$tpls);
        $cols = $r->getColumns();
        $this->assertEquals(6, count($cols));
    }

    public function testGetColumnIndexExists(): void
    {
        $r = new R("listP0", templates: self::$tpls);
        $this->assertEquals("0-60motorcycletimes.com", $r->getColumnIndex("DOMAIN", 0));
    }

    public function testGetColumnIndexNotExists(): void
    {
        $r = new R("listP0", templates: self::$tpls);
        $data = $r->getColumnIndex("COLUMN_NOT_EXISTS", 0);
        $this->assertNull($data);
    }

    public function testGetColumnKeys(): void
    {
        $r = new R("listP0", templates: self::$tpls);
        $colKeys = $r->getColumnKeys();
        $this->assertCount(6, $colKeys);
        $this->assertContains("COUNT", $colKeys);
        $this->assertContains("DOMAIN", $colKeys);
        $this->assertContains("FIRST", $colKeys);
        $this->assertContains("LAST", $colKeys);
        $this->assertContains("LIMIT", $colKeys);
        $this->assertContains("TOTAL", $colKeys);
    }


    public function testGetRecordRows(): void
    {
        $r = new R("listP0", templates: self::$tpls);
        $rec = $r->getRecord(0);
        $this->assertNotNull($rec);
        $this->assertEquals([
            "COUNT" => "2",
            "DOMAIN" => "0-60motorcycletimes.com",
            "FIRST" => "0",
            "LAST" => "1",
            "LIMIT" => "2",
            "TOTAL" => "2701"
        ], $rec->getData());
    }

    public function testGetRecordNoRows(): void
    {
        $r = new R("OK", templates: self::$tpls);
        $this->assertNull($r->getRecord(0));
    }

    public function testGetListHash(): void
    {
        $r = new R("listP0", templates: self::$tpls);
        $lh = $r->getListHash();
        $this->assertCount(2, $lh["LIST"]);
        $this->assertEquals($lh["meta"]["columns"], $r->getColumnKeys(true));
        $this->assertEquals($lh["meta"]["pg"], $r->getPagination());
    }

    public function testPaginationKeysDoNotStripRealColumns(): void
    {
        // RSRMID-2854: columns whose names merely *contain* a pagination keyword
        // as a substring (COUNTRY -> COUNT, FIRSTNAME -> FIRST) must NOT be
        // treated as pagination metadata. Only the exact keys TOTAL/COUNT/LIMIT/
        // FIRST/LAST are pagination columns and get stripped by getColumnKeys(true)
        // and getListHash().
        $raw = implode("\r\n", [
            "[RESPONSE]",
            "CODE=200",
            "DESCRIPTION=Command completed successfully",
            "PROPERTY[TOTAL][0]=2",
            "PROPERTY[FIRST][0]=0",
            "PROPERTY[LAST][0]=1",
            "PROPERTY[COUNT][0]=2",
            "PROPERTY[LIMIT][0]=2",
            "PROPERTY[FIRSTNAME][0]=Adrian",
            "PROPERTY[FIRSTNAME][1]=John",
            "PROPERTY[COUNTRY][0]=PL",
            "PROPERTY[COUNTRY][1]=US",
            "EOF"
        ]);
        $r = new R($raw);

        // unfiltered keys list everything
        $allKeys = $r->getColumnKeys();
        $this->assertContains("FIRSTNAME", $allKeys);
        $this->assertContains("COUNTRY", $allKeys);

        // filtered keys keep the real columns but drop the genuine pagination keys
        $filtered = $r->getColumnKeys(true);
        $this->assertContains("FIRSTNAME", $filtered);
        $this->assertContains("COUNTRY", $filtered);
        foreach (["TOTAL", "COUNT", "LIMIT", "FIRST", "LAST"] as $pgKey) {
            $this->assertNotContains($pgKey, $filtered);
        }

        // list hash rows retain the real columns and drop the pagination keys
        $lh = $r->getListHash();
        $this->assertSame(["columns", "pg"], array_keys($lh["meta"]));
        $this->assertEquals($filtered, $lh["meta"]["columns"]);
        $this->assertSame(["FIRSTNAME" => "Adrian", "COUNTRY" => "PL"], $lh["LIST"][0]);
        $this->assertSame(["FIRSTNAME" => "John", "COUNTRY" => "US"], $lh["LIST"][1]);
    }

    public function testIterationYieldsEveryRecordInOrder(): void
    {
        // The listP0 fixture holds two rows: the first carries the pagination
        // columns alongside DOMAIN, the second only DOMAIN. Iteration walks both
        // and stops — no cursor, no rewind (RSRMID-2939).
        $r = new R("listP0", templates: self::$tpls);

        $rows = [];
        foreach ($r as $index => $rec) {
            $rows[$index] = $rec->getData();
        }

        $this->assertSame([0, 1], array_keys($rows), "iteration must be keyed by record index");
        $this->assertEquals("0-60motorcycletimes.com", $rows[0]["DOMAIN"]);
        $this->assertEquals(["DOMAIN" => "0-be-s01-0.com"], $rows[1]);
    }

    public function testGetPagination(): void
    {
        $r = new R("listP0", templates: self::$tpls);
        $pager = $r->getPagination();
        $this->assertArrayHasKey("COUNT", $pager);
        $this->assertArrayHasKey("CURRENTPAGE", $pager);
        $this->assertArrayHasKey("FIRST", $pager);
        $this->assertArrayHasKey("LAST", $pager);
        $this->assertArrayHasKey("LIMIT", $pager);
        $this->assertArrayHasKey("NEXTPAGE", $pager);
        $this->assertArrayHasKey("PAGES", $pager);
        $this->assertArrayHasKey("PREVIOUSPAGE", $pager);
        $this->assertArrayHasKey("TOTAL", $pager);
    }

    public function testIterationIsRepeatableWithoutARewindStep(): void
    {
        // What the removed cursor could not do: walk the rows twice and get the
        // same rows both times, with nothing to reset in between (RSRMID-2939).
        $r = new R("listP0", templates: self::$tpls);

        $first = [];
        foreach ($r as $rec) {
            $first[] = $rec->getData();
        }
        $second = [];
        foreach ($r as $rec) {
            $second[] = $rec->getData();
        }

        $this->assertCount(2, $first);
        $this->assertSame($first, $second);
    }

    public function testHasNextPageNoRows(): void
    {
        $r = new R("OK", templates: self::$tpls);
        $this->assertEquals(false, $r->hasNextPage());
    }

    public function testHasNextPageRows(): void
    {
        $r = new R("listP0", templates: self::$tpls);
        $this->assertEquals(true, $r->hasNextPage());
    }

    public function testHasPreviousPageNoRows1(): void
    {
        $r = new R("OK", templates: self::$tpls);
        $this->assertEquals(false, $r->hasPreviousPage());
    }

    public function testHasPreviousPageNoRows2(): void
    {
        $r = new R("listP0", templates: self::$tpls);
        $this->assertEquals(false, $r->hasPreviousPage());
    }

    public function testGetLastRecordIndexNoRows(): void
    {
        $r = new R("OK", templates: self::$tpls);
        $this->assertNull($r->getLastRecordIndex());
    }

    public function testGetLastRecordIndexNoLastRows(): void
    {
        $r = implode("\r\n", [
            "[RESPONSE]",
            "CODE=200",
            "DESCRIPTION=Command completed successfully",
            "PROPERTY[DOMAIN][0]=mydomain1.com",
            "PROPERTY[DOMAIN][1]=mydomain2.com",
            "EOF"
        ]);
        $r = new R($r);
        $this->assertEquals(1, $r->getLastRecordIndex());
    }

    public function testGetNextPageNumberNoRows(): void
    {
        $r = new R("OK", templates: self::$tpls);
        $this->assertNull($r->getNextPageNumber());
    }

    public function testGetNextPageNumberRows(): void
    {
        $r = new R("listP0", templates: self::$tpls);
        $this->assertEquals(2, $r->getNextPageNumber());
    }

    public function testGetNextPageNumberLastPage(): void
    {
        // Single-page list (FIRST=0, LIMIT=10, TOTAL=2): the last page has no
        // next page, so getNextPageNumber() must honour the documented null
        // contract rather than clamping to the current page number.
        // Registered on a local registry, not the class-wide one: this template
        // is needed by exactly one test, and widening its scope would be the
        // shared-bag pattern in miniature (RSRMID-2941).
        $tpls = (new RTM())->addTemplate(
            "listLastPage",
            "[RESPONSE]\r\nPROPERTY[TOTAL][0]=2\r\nPROPERTY[FIRST][0]=0\r\n"
            . "PROPERTY[DOMAIN][0]=example1.com\r\nPROPERTY[DOMAIN][1]=example2.com\r\n"
            . "PROPERTY[COUNT][0]=2\r\nPROPERTY[LAST][0]=1\r\nPROPERTY[LIMIT][0]=10\r\n"
            . "DESCRIPTION=Command completed successfully\r\nCODE=200\r\nQUEUETIME=0\r\nRUNTIME=0.023\r\nEOF\r\n"
        );
        $r = new R("listLastPage", templates: $tpls);
        $this->assertEquals(1, $r->getNumberOfPages());
        $this->assertFalse($r->hasNextPage());
        $this->assertNull($r->getNextPageNumber());
    }

    public function testGetNumberOfPages(): void
    {
        $r = new R("OK", templates: self::$tpls);
        $this->assertEquals(0, $r->getNumberOfPages());
    }

    public function testGetPreviousPageNumberNoRows(): void
    {
        $r = new R("OK", templates: self::$tpls);
        $this->assertNull($r->getPreviousPageNumber());
    }

    public function testGetPreviousPageNumberRows(): void
    {
        $r = new R("listP0", templates: self::$tpls);
        $this->assertNull($r->getPreviousPageNumber());
    }

    public function testIteratingAResponseWithoutRecordsYieldsNothing(): void
    {
        $r = new R("OK", templates: self::$tpls);
        $this->assertSame([], iterator_to_array($r));
    }

    public function testConstructorEmptyRaw(): void
    {
        $r = new R("");
        $this->assertEquals(423, $r->getCode());
        $this->assertEquals("Empty API response. Probably unreachable API end point", $r->getDescription());
    }

    public function testInvalidApiResponse(): void
    {
        $r = new R("[RESPONSE]\r\ncode=200\r\nqueuetime=0\r\nEOF\r\n");
        $this->assertEquals(423, $r->getCode());
        $this->assertEquals("Invalid API response. Contact Support", $r->getDescription());
    }

    public function testInvalidApiResponse2(): void
    {
        $r = new R("[RESPONSE]\r\ndescription=\r\ncode=423\r\nqueuetime=0\r\nruntime=0.011\r\nEOF\r\n");
        $this->assertEquals(423, $r->getCode());
        $this->assertEquals("Invalid API response. Contact Support", $r->getDescription());
    }

    public function testGetHash(): void
    {
        $h = (new R(""))->getHash();
        $this->assertEquals("423", $h["CODE"]);
        $this->assertEquals("Empty API response. Probably unreachable API end point", $h["DESCRIPTION"]);
    }

    public function testGetQueuetimeNo(): void
    {
        $r = new R("");
        $this->assertEquals(0, $r->getQueuetime());
    }

    public function testGetQueuetime(): void
    {
        $r = new R("[RESPONSE]\r\ncode=423\r\ndescription=Empty API response. Probably unreachable API end point\r\nqueuetime=0\r\nEOF\r\n");
        $this->assertEquals(0, $r->getQueuetime());
    }

    public function testGetRuntimeNo(): void
    {
        $r = new R("");
        $this->assertEquals(0, $r->getRuntime());
    }

    public function testGetRuntime(): void
    {
        $r = new R("[RESPONSE]\r\ncode=423\r\ndescription=Empty API response. Probably unreachable API end point\r\nruntime=0.12\r\nEOF\r\n");
        $this->assertEquals(0.12, $r->getRuntime());
    }

    public function testIsPendingNo(): void
    {
        $r = new R("");
        $this->assertEquals(false, $r->isPending());
    }

    public function testIsPending(): void
    {
        $r = new R("[RESPONSE]\r\ncode=423\r\ndescription=Empty API response. Probably unreachable API end point\r\npending=1\r\nEOF\r\n");
        $this->assertEquals(true, $r->isPending());
    }

    public function testIsTmpError(): void
    {
        $r = new R("[RESPONSE]\r\ncode=423\r\ndescription=Empty API response. Probably unreachable API end point\r\nEOF\r\n");
        $this->assertEquals(true, $r->isTmpError());
    }
}
