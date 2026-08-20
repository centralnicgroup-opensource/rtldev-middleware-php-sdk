<?php

declare(strict_types=1);

/**
 * CNIC\IBS
 * Copyright © Team Internet Group PLC
 */

namespace CNIC\IBS;

use CNIC\AbstractResponseTranslator;
use CNIC\IBS\ResponseTemplateManager as RTM;
use CNIC\ResponseTemplateManagerInterface;

/**
 * IBS ResponseTranslator
 *
 * @package CNIC\IBS
 */
final class ResponseTranslator extends AbstractResponseTranslator
{
    // NOTE: IBS has no brand-specific message rewrites, so it declares neither
    // description map and inherits the empty defaults from
    // AbstractResponseTranslator (RSRMID-2970). The two rewrite loops in the shared
    // translate() pipeline therefore iterate over nothing and findMatch() is never
    // reached. To add the first IBS rewrite, override the relevant hook here — see
    // CNR\ResponseTranslator for the shape.

    /**
     * A fresh IBS template registry holding the brand's built-ins.
     */
    #[\Override]
    protected static function newTemplateManager(): ResponseTemplateManagerInterface
    {
        return new RTM();
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
     *
     * **The JSON arm is deliberately unanchored** (RSRMID-2972). `/"status":/i`
     * cannot tell a top-level status from one nested under `product[0]`, so a
     * payload whose only status sits inside a product satisfies the check and
     * passes through untouched. Keep it that way. A stricter pattern would make
     * this return true, and
     * {@see \CNIC\AbstractResponseTranslator::translate()} replaces the payload
     * *wholesale* with the "invalid" template — destroying `product[0].code` and
     * `product[0].message` before {@see Response::getCode()} could read them.
     * Those are exactly the fields RTLDEV-16781 exists to surface, so tightening
     * this regex would discard the data the fallback downstream was added to
     * recover.
     *
     * The provisioning failure shape this protects always carries a top-level
     * status too, so the leniency is not currently load-bearing for any observed
     * response — it is load-bearing for the *class* of payload, and the cost of
     * being wrong is silent data loss rather than a visible error. See
     * {@see Response::isError()} for the shapes the API actually sends.
     */
    #[\Override]
    protected static function hasMissingRequiredFields(string $raw): bool
    {
        return (!preg_match("/\"status\":/i", $raw) && !preg_match("/^status=/im", $raw)) // missing status
            || preg_match("/\"status\":\s*\"\"/i", $raw) === 1 // empty status (JSON)
            || preg_match("/^status=\r?$/im", $raw) === 1; // empty status (plain text)
    }
}
