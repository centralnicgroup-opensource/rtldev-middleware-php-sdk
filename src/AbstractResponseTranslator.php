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
 * an explicit $error parameter resolving to the "httperror" template with
 * {HTTPERROR} injection (see {@see resolveTemplateId()}), invalid-template
 * fallback, the two description-map rewrite loops, findMatch(), and placeholder
 * replacement. Only a few narrow points differ, supplied by the abstract hooks
 * below:
 *   - the brand's default template registry (newTemplateManager())
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
 * correct behaviour for both brands.
 *
 * @package CNIC
 */
abstract class AbstractResponseTranslator
{
    /**
     * The brand's default template registry, used when the caller supplies none.
     *
     * A factory, not a shared instance: handing every caller the same object
     * would put the process-global container back that RSRMID-2941 removed —
     * one `addTemplate()` on it would be visible to every later translate().
     */
    abstract protected static function newTemplateManager(): ResponseTemplateManagerInterface;

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
     * @param array{CONNECTION_URL?: string} $placeholders
     * @param string|null $error transport error, if any (see {@see AbstractClient::performRequest()}); non-null means $raw is unusable and the "httperror" template is substituted instead
     * @param ResponseTemplateManagerInterface|null $templates registry to resolve template ids against; null uses the brand's built-ins (see {@see newTemplateManager()})
     */
    public static function translate(
        string $raw,
        array $cmd,
        array $placeholders = [],
        ?string $error = null,
        ?ResponseTemplateManagerInterface $templates = null
    ): string {
        $newraw = $raw === '' || $raw === '0' ? "empty" : $raw;
        // Hint: Empty API Response (replace {CONNECTION_URL} later)

        $rawTemplates = ($templates ?? static::newTemplateManager())->getRawTemplates();

        // Explicit call for a static template, or a declared transport failure
        $templateId = self::resolveTemplateId($newraw, $error, $rawTemplates);
        if ($templateId !== null) {
            // don't use getTemplate as it leads to endless loop as of again
            // creating a response instance
            $newraw = $rawTemplates[$templateId];
            if ($error !== null && $error !== "") {
                $newraw = preg_replace("/\{HTTPERROR\}/", " (" . $error . ")", $newraw) ?? $newraw;
            }
        }

        // Missing or empty required field(s) in API response
        if (static::hasMissingRequiredFields($newraw) && array_key_exists("invalid", $rawTemplates)) {
            $newraw = $rawTemplates["invalid"];
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

        return static::replacePlaceholders($newraw, $placeholders);
    }

    /**
     * Resolve the template id to look up for this response, or null when
     * there is no matching template — in which case the caller falls through
     * to the hasMissingRequiredFields()/"invalid" path, exactly as it did
     * before this method existed.
     *
     * A non-null $error resolves to "httperror", taking priority over $raw —
     * this is what replaced the former "httperror|" sentinel that used to be
     * smuggled through $raw itself — but ONLY if $rawTemplates actually
     * declares that id. The registry is caller-supplied: a third-party brand's
     * need not include "httperror" at all, and indexing
     * $rawTemplates["httperror"] unconditionally would degrade an
     * Undefined-array-key warning into a TypeError a few lines later. Null
     * here, same as any other unmatched id, keeps that a quiet fallback
     * instead of a crash — mirroring the "invalid" lookup a few lines down in
     * translate(), which is guarded by the same array_key_exists() shape.
     *
     * Otherwise $raw is checked against $rawTemplates: a raw payload equal to a
     * known template id (e.g. "empty", "invalid", or one registered via
     * {@see ResponseTemplateManagerInterface::addTemplate()}) is the sanctioned
     * mocking route CLAUDE.md documents for tests — constructing a
     * Response/Translator call with the template id as $raw is how a test
     * exercises a specific canned response without a real API round-trip.
     * This is deliberate, load-bearing behaviour, not a leak. An arbitrary raw
     * response that does not match any known id resolves to null and is used
     * as-is by the caller.
     *
     * $rawTemplates is passed in rather than re-read from the registry: the
     * caller already bound one snapshot for the rest of translate(), and a
     * second read would resolve the id against a possibly different snapshot
     * than the one it is then dereferenced against.
     * @param array<array-key, string> $rawTemplates the caller's already-bound registry snapshot
     */
    private static function resolveTemplateId(string $raw, ?string $error, array $rawTemplates): ?string
    {
        if ($error !== null) {
            return array_key_exists("httperror", $rawTemplates) ? "httperror" : null;
        }
        return array_key_exists($raw, $rawTemplates) ? $raw : null;
    }

    /**
     * Finds a match in the given text and performs replacements based on patterns and placeholders.
     *
     * This function searches for a specified regular expression pattern in the provided text and
     * performs replacements based on the matched pattern, command data, and placeholder values.
     *
     * $subject is mutated in place when a replacement is applied.
     *
     * @param array<string, string> $cmd The command data containing replacements, if applicable.
     */
    private static function findMatch(string $regex, string &$subject, string $replacement, array $cmd): bool
    {
        // match the response for given description
        // NOTE: we match if the description starts with the given description
        // it would also match if it is followed by additional text
        $field = static::fieldName();
        $qregex = "/" . $field . "\s*=\s*" . $regex . "([^\\r\\n]+)?/i";
        $return = false;

        if (preg_match($qregex, $subject)) {
            if (isset($cmd["COMMAND"])) {
                $replacement = str_replace("{COMMAND}", $cmd["COMMAND"], $replacement);
            }

            $tmp = preg_replace($qregex, $field . "=" . $replacement, $subject);
            if ($tmp !== null && $tmp !== $subject) {
                $subject = $tmp;
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
     * @param array{CONNECTION_URL?: string} $placeholders
     */
    protected static function replacePlaceholders(string $raw, array $placeholders): string
    {
        $field = static::fieldName();
        $tmp = preg_replace_callback(
            '/^(' . $field . '\s*=\s*)(.*)$/im',
            static function ($matches) use ($placeholders) {
                $description = $matches[2];

                if (strpos($description, '{') === false) {
                    return $matches[0];
                }

                $description = preg_replace_callback(
                    '/\{([^}]+)\}/',
                    static function ($tokenMatches) use ($placeholders) {
                        $token = $tokenMatches[1];

                        if (array_key_exists($token, $placeholders)) {
                            return $placeholders[$token];
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
