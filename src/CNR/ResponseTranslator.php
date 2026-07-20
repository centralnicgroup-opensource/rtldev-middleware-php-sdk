<?php

declare(strict_types=1);

/**
 * CNIC\CNR
 * Copyright © Team Internet Group PLC
 */

namespace CNIC\CNR;

use CNIC\AbstractResponseTranslator;
use CNIC\CNR\ResponseTemplateManager as RTM;

/**
 * CNR ResponseTranslator
 *
 * @package CNIC\CNR
 */
final class ResponseTranslator extends AbstractResponseTranslator
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
     * The CNR static template container.
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
     * CNR carries the human-readable text in the DESCRIPTION field.
     */
    #[\Override]
    protected static function fieldName(): string
    {
        return "description";
    }

    /**
     * CNR falls back to the "invalid" template when CODE or DESCRIPTION is
     * missing, or DESCRIPTION is present but empty.
     */
    #[\Override]
    protected static function hasMissingRequiredFields(string $raw): bool
    {
        return !preg_match("/description[\s]*=/i", $raw) // missing description
            || preg_match("/description[\s]*=\r\n/i", $raw) === 1 // empty description
            || !preg_match("/code[\s]*=/i", $raw); // missing code
    }
}
