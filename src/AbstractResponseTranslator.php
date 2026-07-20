<?php

declare(strict_types=1);

/**
 * CNIC
 * Copyright © Team Internet Group PLC
 */

namespace CNIC;

/**
 * Shared base for all registrar ResponseTranslator implementations.
 *
 * The translate()/findMatch() pipeline is identical across brands: empty->"empty",
 * "httperror|" splitting, static-template lookup with {HTTPERROR} injection,
 * invalid-template fallback, the two description-map rewrite loops, findMatch(),
 * and placeholder replacement. Only a few narrow points differ, supplied by the
 * abstract hooks below:
 *   - the static template container (templates())
 *   - the two description rewrite maps (descriptionRegexMap()/descriptionRawPatternMap())
 *   - the response field carrying the human-readable text (fieldName():
 *     "description" for CNR, "message" for IBS)
 *   - the "missing/empty required field" check that triggers the invalid fallback
 *     (hasMissingRequiredFields(): CODE/DESCRIPTION for CNR, status for IBS)
 *
 * Placeholder stripping is deliberately unified on the per-field, per-token callback
 * (formerly CNR-only): unknown {UPPER} tokens are removed only inside the
 * human-readable field, leaving {UPPER} content in other data fields untouched. The
 * former IBS behaviour stripped such tokens globally across the whole response, which
 * risked corrupting legitimate data fields — replacePlaceholders() below is the single
 * correct behaviour for both brands. (Ref: RSRMID-2893.)
 *
 * @package CNIC
 */
abstract class AbstractResponseTranslator
{
    /**
     * The brand's static template container (id => raw template string).
     * @return array<string>
     */
    abstract protected static function templates(): array;

    /**
     * plain-string description keys for translation; keys are preg_quote'd before matching
     * @return array<string, string>
     */
    abstract protected static function descriptionRegexMap(): array;

    /**
     * raw regex pattern keys for translation; keys are used as-is (not preg_quote'd)
     * @return array<string, string>
     */
    abstract protected static function descriptionRawPatternMap(): array;

    /**
     * Name of the response field carrying the human-readable text
     * ("description" for CNR, "message" for IBS).
     */
    abstract protected static function fieldName(): string;

    /**
     * Whether the raw response is missing or has an empty required field
     * (CNR: CODE/DESCRIPTION, IBS: status) and should therefore fall back to the
     * "invalid" template.
     * @param string $raw API raw response (already normalised)
     */
    abstract protected static function hasMissingRequiredFields(string $raw): bool;

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

        $templates = static::templates();

        // Explicit call for a static template
        if (array_key_exists($newraw, $templates)) {
            // don't use getTemplate as it leads to endless loop as of again
            // creating a response instance
            $newraw = $templates[$newraw];
            if ($isHTTPError && strlen($httperror)) {
                $newraw = preg_replace("/\{HTTPERROR\}/", " (" . $httperror . ")", $newraw) ?? $newraw;
            }
        }

        // Missing or empty required field(s) in API response
        if (static::hasMissingRequiredFields($newraw) && array_key_exists("invalid", $templates)) {
            $newraw = $templates["invalid"];
        }

        // generic API response description rewrite
        $data = false;
        foreach (static::descriptionRegexMap() as $regex => $val) {
            $data = self::findMatch(preg_quote($regex, "/"), $newraw, $val, $cmd);
            if ($data) {
                break;
            }
        }
        if (!$data) {
            foreach (static::descriptionRawPatternMap() as $pattern => $val) {
                $data = self::findMatch($pattern, $newraw, $val, $cmd);
                if ($data) {
                    break;
                }
            }
        }

        return static::replacePlaceholders($newraw, $ph);
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
        $field = static::fieldName();
        $qregex = "/" . $field . "\s*=\s*" . $regex . "([^\\r\\n]+)?/i";
        $return = false;

        if (preg_match($qregex, $newraw)) {
            // If "COMMAND" exists in $cmd, replace "{COMMAND}" in $val
            if (isset($cmd["COMMAND"])) {
                $val = str_replace("{COMMAND}", $cmd["COMMAND"], $val);
            }

            // If $newraw matches $qregex, replace with "<field>=" . $val
            $tmp = preg_replace($qregex, $field . "=" . $val, $newraw);
            if ($tmp !== null && $tmp !== $newraw) {
                $newraw = $tmp;
                $return = true;
            }
        }

        return $return;
    }

    /**
     * Replace known placeholders in the human-readable field while preserving
     * literal brace content and unknown-token content in other fields.
     *
     * Operates line-by-line on the brand's field (see fieldName()): provided
     * placeholders are substituted, unknown {UPPER} tokens are stripped, and any
     * other brace content (e.g. lowercase %{i} in SPF records) is left untouched.
     *
     * @param string $raw input response
     * @param array{CONNECTION_URL?: string} $ph placeholder key-value pairs
     */
    protected static function replacePlaceholders(string $raw, array $ph): string
    {
        $field = static::fieldName();
        $tmp = preg_replace_callback(
            '/^(' . $field . '\s*=\s*)(.*)$/im',
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
