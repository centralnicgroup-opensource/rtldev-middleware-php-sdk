<?php

declare(strict_types=1);

namespace CNICTEST\CNR;

use CNIC\CNR\Column;
use CNIC\CNR\Response as R;
use PHPUnit\Framework\TestCase;

/**
 * CNR-specific column behaviour.
 *
 * The key/data/length/bounds contract is shared and covered once in
 * CNICTEST\ColumnTest against CNIC\Column. What is CNR's own is the value type:
 * CNR responses are plaintext, so the column binds TValue to string and narrows
 * getDataByIndex() to ?string. These tests cover that narrowing and the wiring
 * that makes CNR\Response build this type.
 */
final class ColumnTest extends TestCase
{
    private const array DOMAINS = ["mydomain1.com", "mydomain2.com", "mydomain3.com"];

    public function testGetKey(): void
    {
        $col = new Column("DOMAIN", self::DOMAINS);
        $this->assertSame("DOMAIN", $col->getKey());
    }

    public function testGetData(): void
    {
        $col = new Column("DOMAIN", self::DOMAINS);
        $this->assertSame(self::DOMAINS, $col->getData());
        $this->assertSame(3, $col->length);
    }

    /**
     * The narrowed return type is the entire reason this subclass exists: a
     * CNR-typed column yields ?string where the shared base yields mixed.
     */
    public function testGetDataByIndexReturnsStringOrNull(): void
    {
        $col = new Column("DOMAIN", self::DOMAINS);
        $this->assertSame("mydomain1.com", $col->getDataByIndex(0));
        $this->assertSame("mydomain3.com", $col->getDataByIndex(2));
        $this->assertNull($col->getDataByIndex(3));
        $this->assertNull($col->getDataByIndex(-1));
    }

    /**
     * Guards the addColumn() wiring: CNR\Response must build the CNR column
     * type, not the shared base, so consumers keep the narrowed return type.
     */
    public function testResponseBuildsCnrColumnType(): void
    {
        $raw = "[RESPONSE]\r\nPROPERTY[DOMAIN][0]=mydomain1.com\r\n"
            . "PROPERTY[DOMAIN][1]=mydomain2.com\r\nDESCRIPTION=Command completed successfully\r\n"
            . "CODE=200\r\nEOF\r\n";
        $col = (new R($raw))->getColumn("DOMAIN");
        $this->assertInstanceOf(Column::class, $col);
        $this->assertSame(["mydomain1.com", "mydomain2.com"], $col->getData());
        $this->assertSame("mydomain2.com", $col->getDataByIndex(1));
        $this->assertNull($col->getDataByIndex(2));
    }
}
