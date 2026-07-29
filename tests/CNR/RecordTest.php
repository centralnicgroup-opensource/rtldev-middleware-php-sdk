<?php

declare(strict_types=1);

namespace CNICTEST\CNR;

use CNIC\CNR\Response as R;
use CNIC\Record;
use PHPUnit\Framework\TestCase;

/**
 * CNR record wiring.
 *
 * Record behaviour is shared and covered once in CNICTEST\RecordTest against
 * CNIC\Record. What is left per brand is the newRecord() factory hook: this
 * asserts CNR\Response builds records from a real CNR list response.
 */
final class RecordTest extends TestCase
{
    public function testRecordFromListResponse(): void
    {
        $raw = "[RESPONSE]\r\nPROPERTY[DOMAIN][0]=mydomain1.com\r\nPROPERTY[RATING][0]=1\r\n"
            . "PROPERTY[DOMAIN][1]=mydomain2.com\r\nPROPERTY[RATING][1]=2\r\n"
            . "DESCRIPTION=Command completed successfully\r\nCODE=200\r\nEOF\r\n";
        $r = new R($raw);

        $rec = $r->getRecord(0);
        $this->assertInstanceOf(Record::class, $rec);
        $this->assertSame(["DOMAIN" => "mydomain1.com", "RATING" => "1"], $rec->getData());
        $this->assertSame("mydomain1.com", $rec->getDataByKey("DOMAIN"));
        $this->assertNull($rec->getDataByKey("KEYNOTEXISTING"));

        $rec = $r->getRecord(1);
        $this->assertNotNull($rec);
        $this->assertSame("mydomain2.com", $rec->getDataByKey("DOMAIN"));
    }
}
