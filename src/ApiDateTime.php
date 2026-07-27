<?php

declare(strict_types=1);

/**
 * CNIC
 * Copyright © Team Internet Group PLC
 */

namespace CNIC;

use CNIC\Exception\InvalidDateTimeException;

/**
 * An immutable, UTC-only date/time value parsed from an API response.
 *
 * The Team Internet APIs declare their date columns in UTC and emit exactly two
 * shapes: a full timestamp (`2026-07-25 07:46:34`, optionally with a fractional
 * second part, as CNR sends) and a bare calendar date (`2030-07-17`, as
 * IBS/Moniker send). This class parses both into one flat struct and does
 * nothing else — it is a **parser, not a formatter**. There is no `in($tz)`, no
 * locale formatting and no ext-intl dependency; presenting a value in the
 * viewer's timezone is a display concern and belongs in the consuming
 * application.
 *
 * Responses are **not** rewritten to use this type. `getPlain()`, `getHash()`
 * and `getListHash()` keep returning the raw API strings; this parser is opt-in
 * at the point where a value is actually used.
 *
 * A bare calendar date names no instant, so {@see self::$ts} and
 * {@see self::$dateTime} are both `null` for one — deliberately, instead of
 * defaulting to midnight, which would be an invented instant a consumer could
 * not tell apart from a real one. {@see self::$date} is always populated.
 *
 * ```php
 * $dt = \CNIC\ApiDateTime::from("2026-07-25 07:46:34");
 * $dt->ts;       // 1784965594
 * $dt->date;     // "2026-07-25"
 * $dt->dateTime; // "2026-07-25 07:46:34"
 *
 * // Presenting it elsewhere is the caller's job:
 * (new \DateTimeImmutable("@{$dt->ts}"))->setTimezone(new \DateTimeZone("Europe/Berlin"));
 * ```
 *
 * @psalm-api
 * @package CNIC
 */
final class ApiDateTime
{
    /**
     * The only timezone this type ever represents. The API declares UTC and the
     * parser refuses offset-bearing input, so the value can never be anything
     * else — see {@see self::$tz}.
     */
    private const string TIMEZONE = "UTC";

    /**
     * Shape gate for the two accepted formats, anchored at both ends.
     *
     * This runs *before* `createFromFormat()` because that function alone is
     * not strict enough: `createFromFormat("!Y-m-d", "2026-7-1")` succeeds with
     * no warning at all, quietly accepting a format the API never sends.
     *
     * Only the space separator is accepted — the ISO `T` variant, a `Z` suffix
     * and numeric offsets are all rejected rather than assumed to mean UTC.
     */
    private const string PATTERN = "/^(?<date>\d{4}-\d{2}-\d{2})(?: (?<time>\d{2}:\d{2}:\d{2})(?:\.\d+)?)?$/";

    /**
     * Unix timestamp of the instant, or `null` when the source value was a bare
     * calendar date and the instant is therefore unknown.
     *
     * `$dt->ts === null` is the unambiguous test for a date-only value.
     */
    public readonly ?int $ts;

    /**
     * The calendar date as `Y-m-d`. Always populated, for both shapes.
     */
    public readonly string $date;

    /**
     * The full timestamp as `Y-m-d H:i:s`, or `null` for a date-only value.
     *
     * It is null rather than falling back to `$date` on purpose: a fallback
     * would make `strtotime($dt->dateTime)` return midnight UTC — precisely the
     * fictitious instant that `$ts === null` exists to refuse. Being null, it
     * fails loudly instead.
     */
    public readonly ?string $dateTime;

    /**
     * Timezone of the source declaration — always `"UTC"`.
     *
     * It is populated even for date-only values, where it describes the
     * declaration rather than an instant.
     */
    public readonly string $tz;

    /**
     * Private by design: instances come from {@see self::from()} /
     * {@see self::tryFrom()} only, which is what makes the UTC invariant
     * structurally unforgeable.
     */
    private function __construct(?int $ts, string $date, ?string $dateTime)
    {
        $this->ts = $ts;
        $this->date = $date;
        $this->dateTime = $dateTime;
        $this->tz = self::TIMEZONE;
    }

    /**
     * Parse an API date/time value.
     *
     * Accepts `Y-m-d H:i:s` (with an optional fractional-second part, which is
     * discarded) and `Y-m-d`. Anything else throws — including values PHP would
     * otherwise roll over silently, such as `2026-02-30` (which
     * `createFromFormat()` turns into `2026-03-02`) or `0000-00-00`.
     *
     * @param string $value Raw value as sent by the API
     * @throws InvalidDateTimeException If the value is not one of the two accepted shapes, or names a non-existent date or time
     */
    public static function from(string $value): self
    {
        if (preg_match(self::PATTERN, $value, $matches) !== 1) {
            throw new InvalidDateTimeException(
                "Unparsable API date/time value: \"{$value}\". Expected \"Y-m-d H:i:s\" or \"Y-m-d\" in UTC."
            );
        }

        $time = $matches["time"] ?? "";
        $isDateOnly = $time === "";
        $format = $isDateOnly ? "!Y-m-d" : "!Y-m-d H:i:s";
        $subject = $isDateOnly ? $matches["date"] : "{$matches["date"]} {$time}";

        $parsed = \DateTimeImmutable::createFromFormat($format, $subject, new \DateTimeZone(self::TIMEZONE));
        $errors = \DateTimeImmutable::getLastErrors();

        // Checking the *warnings* is what catches out-of-range components: PHP
        // reports "The parsed date was invalid" as a warning and still hands back
        // a rolled-over object, so a `$parsed === false` test alone would let
        // 2026-13-45 through as 2027-02-14.
        $rolledOver = $errors !== false && ($errors["error_count"] > 0 || $errors["warning_count"] > 0);

        if ($parsed === false || $rolledOver) {
            throw new InvalidDateTimeException(
                "Non-existent API date/time value: \"{$value}\"."
            );
        }

        return $isDateOnly
            ? new self(null, $parsed->format("Y-m-d"), null)
            : new self($parsed->getTimestamp(), $parsed->format("Y-m-d"), $parsed->format("Y-m-d H:i:s"));
    }

    /**
     * Null-tolerant, non-throwing counterpart of {@see self::from()}.
     *
     * Returns `null` for a `null` input and for anything `from()` would reject.
     * Use it for optional columns that may be absent or empty; use `from()`
     * when an unparsable value is a bug you want to hear about.
     *
     * @param string|null $value Raw value as sent by the API, or null
     */
    public static function tryFrom(?string $value): ?self
    {
        if ($value === null) {
            return null;
        }

        try {
            return self::from($value);
        } catch (InvalidDateTimeException) {
            return null;
        }
    }

    /**
     * Whether the source value was a bare calendar date, naming no instant.
     *
     * Equivalent to `$dt->ts === null`.
     */
    public function isDateOnly(): bool
    {
        return $this->ts === null;
    }

    /**
     * The value as a plain array — ready for `json_encode()` and for handing to
     * a template or frontend.
     *
     * @return array{ts:int|null,date:string,dateTime:string|null,tz:string}
     */
    public function toArray(): array
    {
        return [
            "ts" => $this->ts,
            "date" => $this->date,
            "dateTime" => $this->dateTime,
            "tz" => $this->tz,
        ];
    }
}
