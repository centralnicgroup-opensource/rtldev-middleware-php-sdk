<?php

declare(strict_types=1);

/**
 * CNICTEST
 * Copyright © Team Internet Group PLC
 */

namespace CNICTEST;

use CNIC\AbstractResponseTranslator;
use PHPUnit\Framework\TestCase;

/**
 * Regression coverage for AbstractResponseTranslator::resolveTemplateId()'s
 * "httperror" branch (RSRMID-2937 follow-up).
 *
 * templates() is an abstract hook: a third-party brand translator's container
 * need not declare an "httperror" entry at all. Indexing $templates["httperror"]
 * unconditionally whenever $error !== null would turn a missing key into an
 * Undefined-array-key warning immediately followed by a TypeError a few lines
 * later (preg_replace() against a now-null $newraw) — a real regression from
 * master, which only ever indexed a template after array_key_exists()
 * succeeded and otherwise degraded quietly to the "invalid" template.
 *
 * A purpose-built fixture translator is used rather than mutating a real
 * brand's public static $templates bag: that state has process lifetime and
 * is shared across test classes (the exact defect RSRMID-2941 exists to
 * fix), so mutating it here would make the suite order-dependent.
 */
final class AbstractResponseTranslatorFallbackTest extends TestCase
{
    public function testMissingHttperrorTemplateDegradesToInvalidInsteadOfWarningOrThrowing(): void
    {
        $translator = new class extends AbstractResponseTranslator {
            /**
             * Deliberately no "httperror" key.
             * @return array<string>
             */
            #[\Override]
            protected static function templates(): array
            {
                return [
                    "invalid" => "status=FAILURE\r\nmessage=423 Invalid API response. Contact Support\r\n",
                ];
            }

            /** @return array<string, string> */
            #[\Override]
            protected static function descriptionRegexMap(): array
            {
                return [];
            }

            /** @return array<string, string> */
            #[\Override]
            protected static function descriptionRawPatternMap(): array
            {
                return [];
            }

            #[\Override]
            protected static function fieldName(): string
            {
                return "message";
            }

            #[\Override]
            protected static function hasMissingRequiredFields(string $raw): bool
            {
                return !preg_match("/status\s*=/i", $raw);
            }
        };

        // A non-null $error would select "httperror" on a translator whose
        // templates() declares it; this fixture does not, so resolveTemplateId()
        // must resolve to null and fall through to hasMissingRequiredFields()/
        // "invalid" — exactly the path an ordinary unmatched $raw already takes.
        $result = $translator::translate("some raw payload", [], [], "connection refused");

        $this->assertStringContainsString("423 Invalid API response. Contact Support", $result);
        // The error never reached a template, so it must not leak into the output either.
        $this->assertStringNotContainsString("connection refused", $result);
    }
}
