<?php

#declare(strict_types=1);

/**
 * CNIC\IBS
 * Copyright © CentralNic Group PLC
 */

namespace CNIC\IBS;

use CNIC\CNR\ResponseTemplateManager as RTM;

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

        // Missing status or message in API Response
        if (
            (
                !preg_match("/status[\s]*=/i", $newraw) // missing status
                || preg_match("/status[\s]*=\r\n/i", $newraw) // empty status
                // do not check for message as it is optional in success cases
            )
            && RTM::hasTemplate("invalid")
        ) {
            $newraw = RTM::$templates["invalid"];
        }

        if (empty(self::$descriptionRegexMap)) {
            return $newraw;
        }
        // TODO, check HX if you added something to descriptionRegexMap
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
        if (preg_match("/\{[^}]+\}/", $newraw)) {
            foreach ($ph as $key => $val) {
                $newraw = preg_replace("/\{" . preg_quote($key, "/") . "\}/", $val, $newraw) ?? $newraw;
            }
            $newraw = preg_replace("/\{[^}]+\}/", "", $newraw) ?? $newraw;
            $return = true;
        }

        return $return;
    }
}
