<?php

declare(strict_types=1);

/**
 * CNIC
 * Copyright © Team Internet Group PLC
 */

namespace CNIC;

/**
 * CommandRedactor
 *
 * The single home for "which command keys are sensitive, and how do we mask
 * them" — previously duplicated across {@see AbstractSocketConfig} (which
 * skips `null` values because they are dropped from the request, not logged)
 * and {@see AbstractResponse} (whose command values are never `null`, so the
 * skip is a no-op there, not a behaviour change). Both call sites keep their
 * own `$sensitiveFields` property — the two class hierarchies (SocketConfig
 * side, Response side) must each stay independently safe, since both
 * `CNR\Response` and `IBS\Response` are publicly constructible directly with
 * a raw, unmasked `$cmd` array — this class only removes the duplicated
 * matching/masking algorithm they each ran over their own list.
 *
 * Not part of the SDK's public surface: it helps nobody talk to the API, it
 * only keeps this SDK's own two masking call sites in step. Consumers reach
 * redaction through the `$sensitiveFields` property they already override, so
 * nothing here needs to be callable from outside `CNIC\`.
 *
 * @internal
 * @package CNIC
 */
final class CommandRedactor
{
    /**
     * The replacement value written over a sensitive command value.
     */
    public const string MASK = "***";

    /**
     * Mask the values of the sensitive keys in $command.
     *
     * Matching is case-insensitive, so only the names in $sensitiveFields
     * matter, not their casing versus the command's actual keys. A `null`
     * value is left untouched even when its key matches — the SocketConfig
     * caller relies on this to leave a dropped-from-the-request parameter as
     * `null` rather than turning it into the literal string "***".
     *
     * Builds and returns a new array rather than mutating $command in place,
     * which is what lets the return type track the input's value type
     * (string|null in, string|null out) instead of widening every caller to
     * string|null regardless of whether it ever passes a null.
     *
     * @template TValue of string|null
     * @param array<string, TValue> $command
     * @param string[] $sensitiveFields
     * @return array<string, TValue|string>
     */
    public static function redact(array $command, array $sensitiveFields): array
    {
        $sensitive = array_map(strtolower(...), $sensitiveFields);
        $redacted = [];
        foreach ($command as $key => $val) {
            $redacted[$key] = $val !== null && in_array(strtolower($key), $sensitive, true)
                ? self::MASK
                : $val;
        }
        return $redacted;
    }
}
