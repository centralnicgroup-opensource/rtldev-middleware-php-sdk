<?php

declare(strict_types=1);

/**
 * CNIC\CNR
 * Copyright © Team Internet Group PLC
 */

namespace CNIC\CNR;

use CNIC\CNR\ResponseTemplateManager as RTM;

/**
 * CNR ResponseTranslator
 *
 * @package CNIC\CNR
 */
final class ResponseTranslator
{
    /**
     * plain-string description keys for translation; keys are preg_quote'd before matching
     * @var array<string, string>
     */
    private static array $descriptionRegexMap = [
        // HX - just for future reference, can be cleaned up if we have something similar in place for CNR (used in test automation currently)
        "Authorization failed; Operation forbidden by ACL" => "Authorization failed; Used Command `{COMMAND}` not white-listed by your Access Control List",
        // CNR
        "Missing required attribute; premium domain name. please provide required parameters" => "Confirm the Premium pricing by providing the necessary premium domain price data.",
    ];

    /**
     * raw regex pattern keys for translation; keys are used as-is (not preg_quote'd)
     * @var array<string, string>
     */
    private static array $descriptionRawPatternMap = [
        // HX - just for future reference
        //"Invalid attribute value syntax; resource record \[(.+)\]" => "Invalid Syntax for DNSZone Resource Record: $1",
        //"Missing required attribute; CLASS(?:=| \[MUST BE )PREMIUM_([\w\+]+)[\s\]]" => "Confirm the Premium pricing by providing the parameter CLASS with the value PREMIUM_$1.",
        //"Syntax error in Parameter DOMAIN \((.+)\)" => "The Domain Name $1 is invalid.",
        // CNR
        "Authorization failed.*(?:\[.*(authori[sz]ation (information|code|password)|authinfo).*\]|wrong auth code)" => "The provided Authorization Code (EPP Code) is incorrect. Please verify the correct Authorization Code with the current registrar and try again.",
    ];

    /**
     * translate a raw api response
     * @param string $raw API raw response
     * @param array<string, string> $cmd requested API command
     * @param array{CONNECTION_URL?: string} $ph list of place holder vars
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

        // Missing CODE or DESCRIPTION in API Response
        if (
            (
                !preg_match("/description[\s]*=/i", $newraw) // missing description
                || preg_match("/description[\s]*=\r\n/i", $newraw) // empty description
                || !preg_match("/code[\s]*=/i", $newraw) // missing code
            )
            && RTM::hasTemplate("invalid")
        ) {
            $newraw = RTM::$templates["invalid"];
        }

        // generic API response description rewrite
        $data = false;
        foreach (self::$descriptionRegexMap as $regex => $val) {
            $data = self::findMatch(preg_quote($regex, "/"), $newraw, $val, $cmd);
            if ($data) {
                break;
            }
        }
        if (!$data) {
            foreach (self::$descriptionRawPatternMap as $pattern => $val) {
                $data = self::findMatch($pattern, $newraw, $val, $cmd);
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
     * @param array<string, string> $cmd The command data containing replacements, if applicable.
     */
    private static function findMatch(string $regex, string &$newraw, string $val, array $cmd): bool
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
            if ($tmp !== null && $tmp !== $newraw) {
                $newraw = $tmp;
                $return = true;
            }
        }

        return $return;
    }

    /**
     * Replace known placeholders in DESCRIPTION while preserving literal brace content.
     *
     * @param string $raw input response
     * @param array{CONNECTION_URL?: string} $ph placeholder key-value pairs
     */
    protected static function replacePlaceholders(string $raw, array $ph): string
    {
        $tmp = preg_replace_callback(
            '/^(description\s*=\s*)(.*)$/im',
            static function ($matches) use ($ph) {
                $description = $matches[2];

                if (strpos($description, '{') === false) {
                    return $matches[0];
                }

                $description = preg_replace_callback(
                    '/\{([^}]+)\}/',
                    static function ($tokenMatches) use ($ph) {
                        $token = $tokenMatches[1];

                        if (array_key_exists($token, $ph)) {
                            return $ph[$token];
                        }

                        if (preg_match('/^[A-Z][A-Z0-9_]*$/', $token) === 1) {
                            return '';
                        }

                        return $tokenMatches[0];
                    },
                    $description
                );

                return $matches[1] . ($description ?? $matches[2]);
            },
            $raw
        );

        return $tmp ?? $raw;
    }
}
