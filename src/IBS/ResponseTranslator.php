<?php

declare(strict_types=1);

/**
 * CNIC\IBS
 * Copyright © Team Internet Group PLC
 */

namespace CNIC\IBS;

use CNIC\AbstractResponseTranslator;
use CNIC\IBS\ResponseTemplateManager as RTM;

/**
 * IBS ResponseTranslator
 *
 * @package CNIC\IBS
 */
final class ResponseTranslator extends AbstractResponseTranslator
{
    // NOTE: IBS has no brand-specific message rewrites yet, so both maps below are
    // intentionally empty. While they stay empty the two rewrite loops in the shared
    // translate() pipeline iterate over nothing and findMatch() is never reached — the
    // maps become live as soon as an entry is added here.

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
     * The IBS static template container.
     * @return array<string>
     */
    #[\Override]
    protected static function templates(): array
    {
        return RTM::$templates;
    }

    /**
     * @return array<string, string>
     */
    #[\Override]
    protected static function descriptionRegexMap(): array
    {
        return self::$descriptionRegexMap;
    }

    /**
     * @return array<string, string>
     */
    #[\Override]
    protected static function descriptionRawPatternMap(): array
    {
        return self::$descriptionRawPatternMap;
    }

    /**
     * IBS carries the human-readable text in the message field.
     */
    #[\Override]
    protected static function fieldName(): string
    {
        return "message";
    }

    /**
     * IBS falls back to the "invalid" template when status is missing (JSON or
     * plain) or present but empty. message is optional in success cases and is
     * deliberately not checked.
     */
    #[\Override]
    protected static function hasMissingRequiredFields(string $raw): bool
    {
        return (!preg_match("/\"status\":/i", $raw) && !preg_match("/^status=/im", $raw)) // missing status
            || preg_match("/\"status\":\s*\"\"/i", $raw) === 1 // empty status (JSON)
            || preg_match("/^status=\r?$/im", $raw) === 1; // empty status (plain text)
    }
}
