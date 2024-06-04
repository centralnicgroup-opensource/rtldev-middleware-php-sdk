<?php

#declare(strict_types=1);

/**
 * CNIC\HEXONET
 * Copyright © CentralNic Group PLC
 */

namespace CNIC\HEXONET;

use CNIC\HEXONET\ResponseTemplateManager as RTM;

/**
 * HEXONET ResponseTranslator
 *
 * @package CNIC\HEXONET
 */
class ResponseTranslator
{
    /**
     * hidden class var of API description regex mappings for translation
     * @var array<mixed>
     */
    private static $descriptionRegexMap = [
        // HX
        "Authorization failed; Operation forbidden by ACL" => "Authorization failed; Used Command `{COMMAND}` not white-listed by your Access Control List",
        "Request is not available; DOMAIN TRANSFER IS PROHIBITED BY STATUS (clientTransferProhibited)/WRONG AUTH" => "This Domain is locked and the given Authorization Code is wrong. Initiating a Transfer is therefore impossible.",
        "Request is not available; DOMAIN TRANSFER IS PROHIBITED BY STATUS (clientTransferProhibited)" => "This Domain is locked. Initiating a Transfer is therefore impossible.",
        "Request is not available; DOMAIN TRANSFER IS PROHIBITED BY STATUS (requested)" => "Registration of this Domain Name has not yet completed. Initiating a Transfer is therefore impossible.",
        "Request is not available; DOMAIN TRANSFER IS PROHIBITED BY STATUS (requestedcreate)" => "Registration of this Domain Name has not yet completed. Initiating a Transfer is therefore impossible.",
        "Request is not available; DOMAIN TRANSFER IS PROHIBITED BY STATUS (requesteddelete)" => "Deletion of this Domain Name has been requested. Initiating a Transfer is therefore impossible.",
        "Request is not available; DOMAIN TRANSFER IS PROHIBITED BY STATUS (pendingdelete)" => "Deletion of this Domain Name is pending. Initiating a Transfer is therefore impossible.",
        "Request is not available; DOMAIN TRANSFER IS PROHIBITED BY WRONG AUTH" => "The given Authorization Code is wrong. Initiating a Transfer is therefore impossible.",
        "Request is not available; DOMAIN TRANSFER IS PROHIBITED BY AGE OF THE DOMAIN" => "This Domain Name is within 60 days of initial registration. Initiating a Transfer is therefore impossible.",
        "Attribute value is not unique; DOMAIN is already assigned to your account" => "You cannot transfer a domain that is already on your account at the registrar's system.",
        // CNR
        "Missing required attribute; premium domain name. please provide required parameters" => "Confirm the Premium pricing by providing the necessary premium domain price data.",
        "SkipPregQuote" => [
            // HX
            "Invalid attribute value syntax; resource record \[(.+)\]" => "Invalid Syntax for DNSZone Resource Record: $1",
            "Missing required attribute; CLASS(?:=| \[MUST BE )PREMIUM_([\w\+]+)[\s\]]" => "Confirm the Premium pricing by providing the parameter CLASS with the value PREMIUM_$1.",
            "Syntax error in Parameter DOMAIN \((.+)\)" => "The Domain Name $1 is invalid."
        ]
    ];

    /**
     * translate a raw api response
     * @param String $raw API raw response
     * @param array<string> $cmd requested API command
     * @param array<string> $ph list of place holder vars
     * @return String
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
                $newraw = preg_replace("/\{HTTPERROR\}/", " (" . $httperror . ")", $newraw);
            }
        }

        // Missing CODE or DESCRIPTION in API Response
        if (
            (
                $newraw === null
                || !preg_match("/description[\s]*=/i", $newraw) // missing description
                || preg_match("/description[\s]*=\r\n/i", $newraw) // empty description
                || !preg_match("/code[\s]*=/i", $newraw) // missing code
            )
            && RTM::hasTemplate("invalid")
        ) {
            $newraw = RTM::$templates["invalid"];
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
     * @param array<string> $cmd The command data containing replacements, if applicable.
     * @param array<string> $ph An array of placeholder values for further replacements.
     *
     * @return bool Returns true if replacements were performed, false otherwise.
     */
    protected static function findMatch($regex, &$newraw, $val, $cmd, $ph)
    {
        // match the response for given description
        // NOTE: we match if the description starts with the given description
        // it would also match if it is followed by additional text
        $qregex = "/description\s*=\s*" . $regex . "([^\\r\\n]+)?/i";
        $return = false;

        if (preg_match($qregex, $newraw)) {
            // If "COMMAND" exists in $cmd, replace "{COMMAND}" in $val
            if (isset($cmd["COMMAND"])) {
                $val = str_replace("{COMMAND}", $cmd["COMMAND"], $val);
            }

            // If $newraw matches $qregex, replace with "description=" . $val
            $tmp = preg_replace($qregex, "description=" . $val, $newraw);
            if ($tmp !== null && strcmp($tmp, $newraw) !== 0) {
                $newraw = $tmp;
                $return = true;
            }
        }

        // Generic replacing of placeholder vars
        if (preg_match("/\{[^}]+\}/", $newraw)) {
            foreach ($ph as $key => $val) {
                if ($newraw === null) {
                    continue;
                }
                $newraw = preg_replace("/\{" . preg_quote($key) . "\}/", $val, $newraw);
            }
            if ($newraw === null) {
                return $return;
            }

            $newraw = preg_replace("/\{[^}]+\}/", "", $newraw);
            $return = true;
        }

        return $return;
    }
}
