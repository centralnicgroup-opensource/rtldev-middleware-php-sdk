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

    public function testUnalignedOffsetWindowPredicatesAndPageNumbersAgree(): void
    {
        // RSRMID-2943: FIRST=50 is not a multiple of LIMIT=100, so "page 1" is
        // an unaligned window (offsets 50..149) inside a 1858-record list. Pins
        // that hasNextPage()/hasPreviousPage() and the page-number getters are
        // all derived from the same offset grid, so they cannot disagree: the
        // next request starts at LAST+1=150, which lands on page
        // intdiv(150,100)+1=2, and the previous one starts at
        // max(0,50-100)=0, landing on page 1.
        $tpls = (new RTM())->addTemplate(
            "unalignedWindow",
            "[RESPONSE]\r\nPROPERTY[TOTAL][0]=1858\r\nPROPERTY[FIRST][0]=50\r\n"
            . "PROPERTY[COUNT][0]=100\r\nPROPERTY[LAST][0]=149\r\nPROPERTY[LIMIT][0]=100\r\n"
            . "DESCRIPTION=Command completed successfully\r\nCODE=200\r\nQUEUETIME=0\r\nRUNTIME=0.023\r\nEOF\r\n"
        );
        $r = new R("unalignedWindow", templates: $tpls);
        $this->assertTrue($r->hasNextPage());
        $this->assertEquals(2, $r->getNextPageNumber());
        $this->assertTrue($r->hasPreviousPage());
        $this->assertEquals(1, $r->getPreviousPageNumber());
        $this->assertEquals(1, $r->getCurrentPageNumber());
    }

    public function testWindowAlreadyHoldingTheTailHasNoNextPage(): void
    {
        // The wasted-round-trip fix (RSRMID-2943): FIRST=50, LIMIT=100,
        // LAST=149, TOTAL=150 — this window already holds the tail of the
        // list (LAST+1 === TOTAL). The old predicate compared whole page
        // numbers (getCurrentPageNumber() + 1 <= getNumberOfPages()) and said
        // "true" here, because ceil(150/100) = 2 pages exist even though this
        // window already covers every row — costing an empty follow-up
        // request. The offset-grid predicate answers false directly.
        $tpls = (new RTM())->addTemplate(
            "windowHoldsTail",
            "[RESPONSE]\r\nPROPERTY[TOTAL][0]=150\r\nPROPERTY[FIRST][0]=50\r\n"
            . "PROPERTY[COUNT][0]=100\r\nPROPERTY[LAST][0]=149\r\nPROPERTY[LIMIT][0]=100\r\n"
            . "DESCRIPTION=Command completed successfully\r\nCODE=200\r\nQUEUETIME=0\r\nRUNTIME=0.023\r\nEOF\r\n"
        );
        $r = new R("windowHoldsTail", templates: $tpls);
        $this->assertFalse($r->hasNextPage());
        $this->assertNull($r->getNextPageNumber());
    }

    public function testAbsentPaginationColumnsAreNowRepresentable(): void
    {
        // RSRMID-2943: a non-list response (no FIRST/LAST/LIMIT/TOTAL columns
        // at all) that still carries rows used to have its total/limit fall
        // back to the record count, indistinguishable from a real list whose
        // total/limit genuinely equalled that count. Now it reports "absent"
        // honestly, and derives a single implicit page from the record list.
        $raw = implode("\r\n", [
            "[RESPONSE]",
            "CODE=200",
            "DESCRIPTION=Command completed successfully",
            "PROPERTY[DOMAIN][0]=mydomain1.com",
            "PROPERTY[DOMAIN][1]=mydomain2.com",
            "EOF"
        ]);
        $r = new R($raw);
        $this->assertNull($r->getRecordsTotalCount());
        $this->assertNull($r->getRecordsLimitation());
        $this->assertEquals(1, $r->getNumberOfPages());
        $this->assertFalse($r->hasNextPage());
        $this->assertNull($r->getCurrentPageNumber());
    }

    public function testZeroLimitIsDistinctFromAbsentLimit(): void
    {
        // RSRMID-2943: LIMIT=0 is a real, requested value (a caller explicitly
        // asked for a zero-row window) and must stay distinguishable from "no
        // LIMIT column at all" — the two used to collide because
        // getRecordsLimitation() fell back to getRecordsCount() whenever the
        // column was missing, and 0 was also what an empty record list
        // produced.
        // Verbatim capture of `QueryDomainList` with FIRST=0/LIMIT=0, COLUMN row
        // included. The window is empty and CNR answers LAST = FIRST (= 0 here),
        // not a row index — so LAST+1 < TOTAL holds and only the LIMIT<=0 gate
        // stops requestNextResponsePage() advancing to offset 1.
        $tpls = (new RTM())->addTemplate(
            "zeroLimit",
            "[RESPONSE]\r\nPROPERTY[COLUMN][0]=domain\r\nPROPERTY[COUNT][0]=0\r\nPROPERTY[FIRST][0]=0\r\n"
            . "PROPERTY[LAST][0]=0\r\nPROPERTY[LIMIT][0]=0\r\nPROPERTY[TOTAL][0]=1825820\r\n"
            . "DESCRIPTION=Command completed successfully\r\nCODE=200\r\nQUEUETIME=0\r\nRUNTIME=0.377\r\nEOF\r\n"
        );
        $r = new R("zeroLimit", templates: $tpls);
        $this->assertSame(0, $r->getRecordsLimitation());
        $this->assertFalse($r->hasNextPage());

        $absent = new R("OK", templates: self::$tpls);
        $this->assertNull($absent->getRecordsLimitation());
    }

    public function testPastTheEndWindowWithZeroLimitHasNoNextPage(): void
    {
        // Verbatim capture: FIRST past the end AND LIMIT=0. CNR echoes
        // LAST = FIRST for the empty window, so LAST+1 < TOTAL is false here on
        // the arithmetic alone — but TOTAL is the only thing making that true,
        // and the LIMIT<=0 gate is what the client actually relies on. Pinned
        // next to the FIRST=0/LIMIT=0 capture above because the two differ only
        // in FIRST, which is exactly what shows LAST tracks FIRST rather than
        // being a flat floor.
        $tpls = (new RTM())->addTemplate(
            "pastTheEndZeroLimit",
            "[RESPONSE]\r\nPROPERTY[COLUMN][0]=domain\r\nPROPERTY[COUNT][0]=0\r\nPROPERTY[FIRST][0]=2000000\r\n"
            . "PROPERTY[LAST][0]=2000000\r\nPROPERTY[LIMIT][0]=0\r\nPROPERTY[TOTAL][0]=1825824\r\n"
            . "DESCRIPTION=Command completed successfully\r\nCODE=200\r\nQUEUETIME=0\r\nRUNTIME=18.906\r\nEOF\r\n"
        );
        $r = new R("pastTheEndZeroLimit", templates: $tpls);
        $this->assertSame(0, $r->getRecordsLimitation());
        $this->assertFalse($r->hasNextPage());
        $this->assertNull($r->getNextPageNumber());
    }

    public function testPastTheEndWindowWithPositiveLimitHasNoNextPage(): void
    {
        // Verbatim capture: FIRST=20000000 (past the end) with LIMIT=10, so the
        // LIMIT<=0 gate does NOT apply and the offset arithmetic has to carry
        // this one by itself. It does, because CNR echoes LAST = FIRST for the
        // empty window: 20000001 < 1825824 is false. This is the shape that
        // would break a predicate derived from whole page numbers instead.
        $tpls = (new RTM())->addTemplate(
            "pastTheEndLimited",
            "[RESPONSE]\r\nPROPERTY[COLUMN][0]=domain\r\nPROPERTY[COUNT][0]=0\r\nPROPERTY[FIRST][0]=20000000\r\n"
            . "PROPERTY[LAST][0]=20000000\r\nPROPERTY[LIMIT][0]=10\r\nPROPERTY[TOTAL][0]=1825824\r\n"
            . "DESCRIPTION=Command completed successfully\r\nCODE=200\r\nQUEUETIME=0\r\nRUNTIME=15.892\r\nEOF\r\n"
        );
        $r = new R("pastTheEndLimited", templates: $tpls);
        $this->assertFalse($r->hasNextPage());
        $this->assertNull($r->getNextPageNumber());
    }

    public function testWindowEndingBeforeItStartsHasNoNextPage(): void
    {
        // Synthetic, and deliberately so: NO observed CNR response answers
        // LAST < FIRST — an empty window echoes LAST = FIRST (see the two
        // captures above). This pins the invariant the client's advance rests
        // on rather than a shape the API produces: because LAST >= FIRST
        // always, requestNextResponsePage()'s FIRST = LAST+1 strictly
        // increases and the walk is monotonic. A wire change or a substitute
        // parser that broke that would send pagination BACKWARD — re-listing
        // the account from near the start — instead of failing, which is why
        // hasNextPage() refuses it rather than trusting the arithmetic.
        $tpls = (new RTM())->addTemplate(
            "backwardWindow",
            "[RESPONSE]\r\nPROPERTY[COLUMN][0]=domain\r\nPROPERTY[COUNT][0]=0\r\nPROPERTY[FIRST][0]=2000000\r\n"
            . "PROPERTY[LAST][0]=0\r\nPROPERTY[LIMIT][0]=100\r\nPROPERTY[TOTAL][0]=1825824\r\n"
            . "DESCRIPTION=Command completed successfully\r\nCODE=200\r\nQUEUETIME=0\r\nRUNTIME=0.377\r\nEOF\r\n"
        );
        $r = new R("backwardWindow", templates: $tpls);
        $this->assertFalse($r->hasNextPage());
        $this->assertNull($r->getNextPageNumber());
    }

    public function testAWindowWithNoTotalHasNoNextPage(): void
    {
        // Synthetic, like the gate above, but read the scope of this one
        // carefully before treating it as a behavioural guard, because it is
        // not one and a later reader should not have to re-derive that.
        //
        // hasNextPage() null-checks FIRST, LAST and TOTAL together. On CNR only
        // TOTAL can actually be null by the time that line runs: reaching it
        // needs a positive LIMIT, a LIMIT column means getRecordsCount() >= 1,
        // and both getFirstRecordIndex() and getLastRecordIndex() fall back to
        // a record-derived value rather than null once there is a record. The
        // other two clauses are there for the analysers — `$last < $first` and
        // `$last + 1 < $total` need int, not ?int — and for a future brand
        // whose readers have no such fallback.
        //
        // The TOTAL clause is inert as well: PHP coerces the null to 0, so
        // `2 < null` is false and the answer would be the same without it. So
        // what this test pins is the RSRMID-2943 ?int contract — an absent
        // pagination column reads as null, not as 0 — and that hasNextPage()
        // answers false for a response that cannot say how long the list is.
        // Do not "prove" it by deleting a clause and expecting red.
        $tpls = (new RTM())->addTemplate(
            "listWithoutTotal",
            "[RESPONSE]\r\nPROPERTY[COLUMN][0]=domain\r\nPROPERTY[COUNT][0]=2\r\nPROPERTY[FIRST][0]=0\r\n"
            . "PROPERTY[LAST][0]=1\r\nPROPERTY[LIMIT][0]=100\r\n"
            . "DESCRIPTION=Command completed successfully\r\nCODE=200\r\nQUEUETIME=0\r\nRUNTIME=0.377\r\nEOF\r\n"
        );
        $r = new R("listWithoutTotal", templates: $tpls);
        $this->assertSame(100, $r->getRecordsLimitation());
        $this->assertNull($r->getRecordsTotalCount());
        $this->assertFalse($r->hasNextPage());
        $this->assertNull($r->getNextPageNumber());
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
