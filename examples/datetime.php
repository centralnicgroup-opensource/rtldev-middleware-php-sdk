<?php

declare(strict_types=1);

use CNIC\ApiDateTime;
use CNIC\Column;
use CNIC\Exception\InvalidDateTimeException;
use CNIC\Record;

require __DIR__ . '/../vendor/autoload.php';

// This demo needs no credentials and makes no API calls — ApiDateTime is a pure
// parser for the date/time strings the APIs return.

echo "--- CNR: full timestamp ----\n\n";
$cnr = ApiDateTime::from("2026-07-25 07:46:34");
echo print_r($cnr->toArray(), true);
echo "isDateOnly(): " . var_export($cnr->isDateOnly(), true) . "\n";

echo "\n--- IBS / Moniker: date-only value ----\n\n";
// A bare calendar date names no instant, so ts and dateTime are BOTH null rather
// than defaulting to midnight — that would be an invented instant. date is
// always populated, so there is unconditionally something to print.
//
// IBS/Moniker actually send "/" as the separator (e.g. "2030/07/17"), which is
// what this demo parses; $date always comes back with "-" regardless.
$ibs = ApiDateTime::from("2030/07/17");
echo print_r($ibs->toArray(), true);
echo "isDateOnly(): " . var_export($ibs->isDateOnly(), true) . "\n";

echo "\n--- CNR: fractional seconds are discarded from dateTime, but kept in raw ----\n\n";
$frac = ApiDateTime::from("2024-12-10 13:17:55.813");
echo "dateTime: {$frac->dateTime}\n"; // "2024-12-10 13:17:55" — whole seconds only
echo "raw:      {$frac->raw}\n";      // "2024-12-10 13:17:55.813" — the input, verbatim

echo "\n--- raw: display/logging only — never compare or sort on it ----\n\n";
// A plain string comparison of "/"-separated against "-"-separated values gives
// the wrong order; $date is always normalized to ISO for exactly this reason.
// raw preserves the source's own separator, so it must not be used for that.
echo "raw:  {$ibs->raw}\n";  // "2030/07/17" — verbatim, whatever the API sent
echo "date: {$ibs->date}\n"; // "2030-07-17" — always ISO, safe to compare/sort

echo "\n--- Rejected: a non-existent date is refused, not coerced ----\n\n";
// PHP's own date handling would silently roll "2026-02-30" over to 2026-03-02.
try {
    ApiDateTime::from("2026-02-30");
    echo "UNEXPECTED: no exception thrown.\n";
} catch (InvalidDateTimeException $e) {
    echo "InvalidDateTimeException: " . $e->getMessage() . "\n";
}

echo "\n--- tryFrom(): null instead of an exception ----\n\n";
var_dump(ApiDateTime::tryFrom(null));
var_dump(ApiDateTime::tryFrom("2026-02-30"));

echo "\n--- Presenting a value in another timezone is the caller's job ----\n\n";
// The SDK deliberately stays UTC-only; localisation belongs in the frontend.
if ($cnr->ts !== null) {
    $local = (new DateTimeImmutable("@{$cnr->ts}"))->setTimezone(new DateTimeZone("Europe/Berlin"));
    echo "UTC:            {$cnr->dateTime}\n";
    echo "Europe/Berlin:  " . $local->format("Y-m-d H:i:s T") . "\n";
}

echo "\n--- Record/Column: opt-in accessors, instead of parsing getDataByKey() yourself ----\n\n";
// getDateTimeByKey()/getDateTimeByIndex() do the is_string()+tryFrom() narrowing
// for you, right where a value is already being read out of a Record/Column.
// Response data itself is never rewritten — these are read-time helpers.
$rec = new Record(["expirationdate" => "2030/07/17", "note" => "n/a"]);
var_dump($rec->getDateTimeByKey("expirationdate")?->date); // "2030-07-17"
var_dump($rec->getDateTimeByKey("note"));                  // null — not parsable, not thrown
var_dump($rec->getDateTimeByKey("missing"));                // null — key absent

$col = new Column("expirationdate", ["2030/07/17"]);
var_dump($col->getDateTimeByIndex(0)?->isDateOnly()); // true
var_dump($col->getDateTimeByIndex(1));                // null — out of range
