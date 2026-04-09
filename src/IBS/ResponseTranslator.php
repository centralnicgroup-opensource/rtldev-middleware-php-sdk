<?php

#declare(strict_types=1);

/**
 * CNIC\IBS
 * Copyright © CentralNic Group PLC
 */

namespace CNIC\IBS;

use CNIC\IBS\ResponseTemplateManager as RTM;

/**
 * IBS ResponseTranslator
 *
 * @package CNIC\IBS
 */
class ResponseTranslator
{
    /**
     * hidden class var of API description regex mappings for translation
     * @var array<mixed>
     */
    private static $descriptionRegexMap = [];

    /**
     * translate a raw api response
     * @param string $raw API raw response
     * @param array<string> $cmd requested API command
     * @param array<string> $ph list of place holder vars
     * @return string
     */
    public static function translate($raw, $cmd, $ph = [])
    {
        $newraw = empty($raw) ? "empty" : $raw;
        // Hint: Empty API Response (replace {CONNECTION_URL} later)

        // curl error handling
        $isHTTPError = substr($newraw, 0, 10) === "httperror|";
        if ($isHTTPError) {
            list($newraw, $httperror) = explode("|", $newraw);
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

        if (empty(self::$descriptionRegexMap)) {
            return self::replacePlaceholders($newraw, $ph);
        }

        // Iterate through the description-to-regex mapping
        // generic API response description rewrite
        $data = false;
        foreach (self::$descriptionRegexMap as $regex => $val) {
            // Check if $regex should be treated as multiple patterns
            if ($regex === "SkipPregQuote") {
                // Iterate through each temporary pattern in $val
                foreach ($val as $tmpRegex => $tmpVal) {
                    // Attempt to find a match using the temporary pattern
                    $data = self::findMatch($tmpRegex, $newraw, $tmpVal, $cmd, $ph);

                    // If a match is found, exit the inner loop
                    if ($data) {
                        break;
                    }
                }
            } else {
                // Escape the pattern and attempt to find a match
                // for the given pattern ($regex)
                $escapedRegex = preg_quote($regex, "/");
                $data = self::findMatch($escapedRegex, $newraw, $val, $cmd, $ph);
            }

            // If a match is found, exit the outer loop
            if ($data) {
                break;
            }
        }

        return $newraw;
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
     * @param array<string> $requestdata The request data containing replacements, if applicable.
     * @param array<string> $ph An array of placeholder values for further replacements.
     *
     * @return bool Returns true if replacements were performed, false otherwise.
     */
    protected static function findMatch($regex, &$newraw, $val, $requestdata, $ph)
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

        // Generic replacing of placeholder vars
        $before = $newraw;
        $newraw = self::replacePlaceholders($newraw, $ph);
        return $return || $newraw !== $before;
    }

    /**
     * Replace placeholder vars like {CONNECTION_URL} in a string
     * @param string $raw input string
     * @param array<string> $ph placeholder key-value pairs
     * @return string
     */
    protected static function replacePlaceholders($raw, $ph)
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
