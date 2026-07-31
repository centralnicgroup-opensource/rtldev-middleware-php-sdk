<?php

declare(strict_types=1);

namespace CNICTEST;

use CNIC\ApiDateTime;
use CNIC\Exception\InvalidDateTimeException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Contract tests for CNIC\ApiDateTime.
 *
 * The parser is deliberately strict and UTC-only: it accepts exactly the two
 * shapes the Team Internet APIs emit and refuses everything else rather than
 * coercing it. These tests pin both halves of that contract — what is accepted
 * and, at least as importantly, what must throw instead of being silently
 * rolled over into a different (valid-looking) instant.
 */
final class ApiDateTimeTest extends TestCase
{
    /**
     * A CNR timestamp carries a real instant, so every field is populated.
     */
    public function testParsesCnrTimestamp(): void
    {
        $dt = ApiDateTime::from("2026-07-25 07:46:34");

        $this->assertSame(1784965594, $dt->ts);
        $this->assertSame("2026-07-25", $dt->date);
        $this->assertSame("2026-07-25 07:46:34", $dt->dateTime);
        $this->assertSame("UTC", $dt->tz);
        $this->assertSame("2026-07-25 07:46:34", $dt->raw);
        $this->assertFalse($dt->isDateOnly());
    }

    /**
     * CNR appends a fractional-second part (e.g. "2024-12-10 13:17:55.0").
     * It is matched and discarded from $dateTime — the SDK's resolution is
     * whole seconds — but it survives verbatim in $raw. This is the regression
     * test that catches someone later "tidying up" by passing the
     * separator-normalised subject (rather than the original $value) into the
     * constructor.
     */
    public function testDiscardsFractionalSeconds(): void
    {
        $dt = ApiDateTime::from("2024-12-10 13:17:55.0");

        $this->assertSame("2024-12-10 13:17:55", $dt->dateTime);
        $this->assertSame(1733836675, $dt->ts);

        $this->assertSame(
            ApiDateTime::from("2024-12-10 13:17:55")->ts,
            ApiDateTime::from("2024-12-10 13:17:55.123456")->ts,
            "A fractional part must not change the resulting instant."
        );
    }

    public function testRawRetainsFractionalSecondsDiscardedByDateTime(): void
    {
        $dt = ApiDateTime::from("2026-07-25 07:46:34.813");

        $this->assertStringEndsWith(".813", $dt->raw);
        $this->assertNotNull($dt->dateTime);
        $this->assertStringNotContainsString(".", $dt->dateTime);
    }

    /**
     * IBS/Moniker emit calendar dates with no time part. The exact instant is
     * unknown, so both instant-bearing fields are null rather than midnight —
     * midnight would be a fabricated instant a consumer could not distinguish
     * from a real one.
     */
    public function testParsesDateOnlyValueWithNullInstantFields(): void
    {
        $dt = ApiDateTime::from("2030-07-17");

        $this->assertNull($dt->ts);
        $this->assertNull($dt->dateTime);
        $this->assertSame("2030-07-17", $dt->date);
        $this->assertSame("UTC", $dt->tz);
        $this->assertSame("2030-07-17", $dt->raw);
        $this->assertTrue($dt->isDateOnly());
    }

    /**
     * IBS/Moniker actually send "/" as the date separator (e.g. "2030/07/17").
     * It is accepted directly and $date always comes back with "-" regardless.
     */
    public function testParsesSlashSeparatedDateOnlyValue(): void
    {
        $dt = ApiDateTime::from("2030/07/17");

        $this->assertNull($dt->ts);
        $this->assertNull($dt->dateTime);
        $this->assertSame("2030-07-17", $dt->date);
        $this->assertSame("UTC", $dt->tz);
        $this->assertSame("2030/07/17", $dt->raw);
        $this->assertTrue($dt->isDateOnly());
    }

    /**
     * The "/" separator is accepted symmetrically with a time part too, and
     * $dateTime always comes back with "-" regardless of the input separator.
     */
    public function testParsesSlashSeparatedTimestamp(): void
    {
        $dt = ApiDateTime::from("2026/07/25 07:46:34");

        $this->assertSame(1784965594, $dt->ts);
        $this->assertSame("2026-07-25", $dt->date);
        $this->assertSame("2026-07-25 07:46:34", $dt->dateTime);
        $this->assertSame("UTC", $dt->tz);
        $this->assertSame("2026/07/25 07:46:34", $dt->raw);
        $this->assertFalse($dt->isDateOnly());
    }

    /**
     * Pins that normalisation to ISO applies to the struct ($date) and not to
     * $raw: for "/" input the two must differ.
     */
    public function testRawPreservesSlashSeparatorWhileDateIsAlwaysIso(): void
    {
        $dt = ApiDateTime::from("2026/02/20");

        $this->assertSame("2026/02/20", $dt->raw);
        $this->assertSame("2026-02-20", $dt->date);
        $this->assertNotSame($dt->raw, $dt->date);
    }

    /**
     * `date` is populated unconditionally, so a consumer that just wants
     * something printable never has to branch on isDateOnly().
     */
    public function testDateIsAlwaysPopulated(): void
    {
        $this->assertNotSame("", ApiDateTime::from("2030-07-17")->date);
        $this->assertNotSame("", ApiDateTime::from("2026-07-25 07:46:34")->date);
    }

    public function testToArrayForTimestamp(): void
    {
        $this->assertSame(
            [
                "ts" => 1784965594,
                "date" => "2026-07-25",
                "dateTime" => "2026-07-25 07:46:34",
                "tz" => "UTC",
                "raw" => "2026-07-25 07:46:34",
            ],
            ApiDateTime::from("2026-07-25 07:46:34")->toArray()
        );
    }

    public function testToArrayForDateOnly(): void
    {
        $this->assertSame(
            [
                "ts" => null,
                "date" => "2030-07-17",
                "dateTime" => null,
                "tz" => "UTC",
                "raw" => "2030-07-17",
            ],
            ApiDateTime::from("2030-07-17")->toArray()
        );
    }

    /**
     * The API declares UTC, so the parse must not depend on the process-wide
     * default timezone — a server configured for another zone has to produce
     * the very same epoch second.
     */
    public function testInstantIsIndependentOfDefaultTimezone(): void
    {
        $previous = date_default_timezone_get();
        date_default_timezone_set("America/New_York");

        try {
            $this->assertSame(1784965594, ApiDateTime::from("2026-07-25 07:46:34")->ts);
        } finally {
            date_default_timezone_set($previous);
        }
    }

    public function testAcceptsLeapDayInLeapYear(): void
    {
        $this->assertSame("2024-02-29", ApiDateTime::from("2024-02-29")->date);
    }

    /**
     * Values PHP's own date handling would silently accept and roll over into a
     * different instant, plus shapes that are simply not the API's format.
     * Every one of them must throw instead.
     *
     * @return array<string, array{string}>
     */
    public static function rejectedValues(): array
    {
        return [
            "unpadded month and day" => ["2026-7-1"],
            "month and day out of range" => ["2026-13-45"],
            "day past end of month" => ["2026-02-30"],
            "leap day in non-leap year" => ["2026-02-29"],
            "zero date" => ["0000-00-00"],
            "hour out of range" => ["2026-07-25 25:00:00"],
            "minute out of range" => ["2026-07-25 12:60:00"],
            "second out of range" => ["2026-07-25 12:00:61"],
            "numeric offset suffix" => ["2026-07-25T07:46:34+02:00"],
            "zulu suffix" => ["2026-07-25 07:46:34Z"],
            "iso T separator" => ["2026-07-25T07:46:34"],
            "surrounding whitespace" => [" 2026-07-25 07:46:34 "],
            "time without seconds" => ["2026-07-25 07:46"],
            "empty string" => [""],
            "unix timestamp" => ["1784965594"],
            "free text" => ["not a date"],
            // The same strictness cases, repeated in the "/" separator form.
            "unpadded month and day (slash)" => ["2026/2/1"],
            "month and day out of range (slash)" => ["2026/13/45"],
            "day past end of month (slash)" => ["2026/02/30"],
            "zero date (slash)" => ["0000/00/00"],
            // Consistency between the two halves is required — a mix of both
            // separators in one value is rejected rather than tolerated.
            "mixed separators, dash then slash" => ["2026-02/20"],
            "mixed separators, slash then dash" => ["2026/02-20"],
            // Without the `D` modifier, PCRE's "$" also matches immediately
            // before a single trailing newline; these pin that it does not.
            "trailing newline, date only" => ["2026-07-25\n"],
            "trailing newline, date only (slash)" => ["2030/07/17\n"],
            "trailing newline, timestamp" => ["2026-07-25 07:46:34\n"],
            "trailing newline, timestamp (slash)" => ["2026/07/25 07:46:34\n"],
        ];
    }

    #[DataProvider("rejectedValues")]
    public function testRejectsMalformedOrRolledOverValues(string $value): void
    {
        $this->expectException(InvalidDateTimeException::class);
        ApiDateTime::from($value);
    }

    /**
     * The offending value belongs in the message — without it, a failure deep
     * in a list response gives no clue which column was at fault.
     */
    public function testExceptionMessageNamesTheOffendingValue(): void
    {
        try {
            ApiDateTime::from("2026-02-30");
            $this->fail("Expected InvalidDateTimeException was not thrown.");
        } catch (InvalidDateTimeException $e) {
            $this->assertStringContainsString("2026-02-30", $e->getMessage());
        }
    }

    /**
     * tryFrom() is the null-tolerant, non-throwing counterpart — for the common
     * case of an optional API column that may be absent or empty.
     */
    public function testTryFromReturnsNullForNullAndInvalidInput(): void
    {
        $this->assertNull(ApiDateTime::tryFrom(null));
        $this->assertNull(ApiDateTime::tryFrom(""));
        $this->assertNull(ApiDateTime::tryFrom("2026-02-30"));
    }

    /**
     * Mixed separators are rejected by tryFrom() too — null, not a partial parse.
     */
    public function testTryFromReturnsNullForMixedSeparators(): void
    {
        $this->assertNull(ApiDateTime::tryFrom("2026-02/20"));
        $this->assertNull(ApiDateTime::tryFrom("2026/02-20"));
    }

    /**
     * A trailing newline is rejected by tryFrom() too — null, not a partial
     * parse that quietly carries the newline into $raw.
     */
    public function testTryFromReturnsNullForTrailingNewline(): void
    {
        $this->assertNull(ApiDateTime::tryFrom("2026-07-25\n"));
        $this->assertNull(ApiDateTime::tryFrom("2026-07-25 07:46:34\n"));
    }

    public function testTryFromParsesValidInput(): void
    {
        $dt = ApiDateTime::tryFrom("2026-07-25 07:46:34");

        $this->assertInstanceOf(ApiDateTime::class, $dt);
        $this->assertSame(1784965594, $dt->ts);
    }

    public function testTryFromParsesSlashSeparatedInput(): void
    {
        $dt = ApiDateTime::tryFrom("2030/07/17");

        $this->assertInstanceOf(ApiDateTime::class, $dt);
        $this->assertSame("2030-07-17", $dt->date);
    }
}
