<?php

declare(strict_types=1);

/**
 * CNIC\IBS
 * Copyright © Team Internet Group PLC
 */

namespace CNIC\IBS;

use CNIC\IBS\ResponseTemplateManager as RTM;

/**
 * IBS ResponseTranslator
 *
 * @package CNIC\IBS
 */
final class ResponseTranslator
{
    // NOTE: IBS has no brand-specific message rewrites yet, so both maps below are
    // intentionally empty. While they stay empty, translate() returns via the early
    // exit in the "$descriptionRegexMap === [] && $descriptionRawPatternMap === []"
    // branch and findMatch() is never reached — it is kept for structural parity with
    // CNR\ResponseTranslator and becomes live as soon as an entry is added here.

    /**
     * plain-string description keys for translation; keys are preg_quote'd before matching
     * @var array<string, string>
     */
    private static array $descriptionRegexMap = [];

    /**
     * raw regex pattern keys for translation; keys are used as-is (not preg_quote'd)
     * @var array<string, string>
     */
    private static array $descriptionRawPatternMap = [];

    /**
     * translate a raw api response
     * @param string $raw API raw response
     * @param array<string, string> $cmd requested API command
     * @param array{CONNECTION_URL?: string} $ph list of place holder vars
     * @psalm-suppress UnusedParam $cmd kept for API consistency with CNR\ResponseTranslator
     */
    public static function translate(string $raw, array $cmd, array $ph = []): string
    {
        $newraw = $raw === '' || $raw === '0' ? "empty" : $raw;
        // Hint: Empty API Response (replace {CONNECTION_URL} later)

        // curl error handling
        $httperror = "";
        $isHTTPError = substr($newraw, 0, 10) === "httperror|";
        if ($isHTTPError) {
            $parts = explode("|", $newraw, 2);
            $newraw = $parts[0];
            $httperror = $parts[1] ?? "";
        }

        // Explicit call for a static template
        if (RTM::hasTemplate($newraw)) {
            // don't use getTemplate as it leads to endless loop as of again
            // creating a response instance
            $newraw = RTM::$templates[$newraw];
            if ($isHTTPError && strlen($httperror)) {
                $newraw = preg_replace("/\{HTTPERROR\}/", " (" . $httperror . ")", $newraw) ?? $newraw;
            }
        }

        // Missing or empty status in API Response
        if (
            (
                (!preg_match("/\"status\":/i", $newraw) && !preg_match("/^status=/im", $newraw)) // missing status
                || preg_match("/\"status\":\s*\"\"/i", $newraw) // empty status (JSON)
                || preg_match("/^status=\r?$/im", $newraw) // empty status (plain text)
                // do not check for message as it is optional in success cases
            )
            && RTM::hasTemplate("invalid")
        ) {
            $newraw = RTM::$templates["invalid"];
        }

        if (self::$descriptionRegexMap === [] && self::$descriptionRawPatternMap === []) {
            return self::replacePlaceholders($newraw, $ph);
        }

        // generic API response description rewrite
        $data = false;
        foreach (self::$descriptionRegexMap as $regex => $val) {
            $data = self::findMatch(preg_quote($regex, "/"), $newraw, $val);
            if ($data) {
                break;
            }
        }
        if (!$data) {
            foreach (self::$descriptionRawPatternMap as $pattern => $val) {
                $data = self::findMatch($pattern, $newraw, $val);
                if ($data) {
                    break;
                }
            }
        }

        return self::replacePlaceholders($newraw, $ph);
    }

    /**
     * Finds a match in the given text and performs replacements based on patterns and placeholders.
     *
     * This function searches for a specified regular expression pattern in the provided text and
     * performs replacements based on the matched pattern, command data, and placeholder values.
     *
     * @param string $regex The regular expression pattern to search for.
     * @param string $newraw The input text where the match will be searched for and replacements applied.
     * @param string $val The value to be used in replacement if a match is found.
     * @return bool Returns true if replacements were performed, false otherwise.
     */
    private static function findMatch(string $regex, string &$newraw, string $val): bool
    {
        // match the response for given description
        // NOTE: we match if the description starts with the given description
        // it would also match if it is followed by additional text
        $qregex = "/message\s*=\s*" . $regex . "([^\\r\\n]+)?/i";
        $return = false;

        if (preg_match($qregex, $newraw)) {
            // If $newraw matches $qregex, replace with "message=" . $val
            $tmp = preg_replace($qregex, "message=" . $val, $newraw);
            if ($tmp !== null && strcmp($tmp, $newraw) !== 0) {
                $newraw = $tmp;
                $return = true;
            }
        }

        return $return;
    }

    /**
     * Replace placeholder vars like {CONNECTION_URL} in a string
     * @param string $raw input string
     * @param array{CONNECTION_URL?: string} $ph placeholder key-value pairs
     */
    protected static function replacePlaceholders(string $raw, array $ph): string
    {
        if (preg_match("/\{[A-Z][A-Z0-9_]*\}/", $raw)) {
            foreach ($ph as $key => $val) {
                $raw = preg_replace("/\{" . preg_quote($key, "/") . "\}/", $val, $raw) ?? $raw;
            }
            $raw = preg_replace("/\s?\{[A-Z][A-Z0-9_]*\}/", "", $raw) ?? $raw;
        }
        return $raw;
    }
}
