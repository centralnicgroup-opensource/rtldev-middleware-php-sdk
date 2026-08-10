<?php

declare(strict_types=1);

/**
 * CNICTEST
 * Copyright © Team Internet Group PLC
 */

namespace CNICTEST;

use CNIC\AbstractResponseTemplateManager;
use CNIC\AbstractResponseTranslator;
use CNIC\Exception\UnsupportedFeatureException;
use CNIC\IBS\Response as IBSResponse;
use CNIC\IBS\ResponseParser as IBSParser;
use CNIC\ResponseInterface;
use CNIC\ResponseParserInterface;
use CNIC\ResponseTemplateManagerInterface;
use PHPUnit\Framework\TestCase;

/**
 * Regression coverage for AbstractResponseTranslator::resolveTemplateId()'s
 * "httperror" branch (RSRMID-2937 follow-up).
 *
 * The registry is caller-supplied: a third-party brand's need not declare an
 * "httperror" entry at all. Indexing $rawTemplates["httperror"] unconditionally
 * whenever $error !== null would turn a missing key into an Undefined-array-key
 * warning immediately followed by a TypeError a few lines later (preg_replace()
 * against a now-null $newraw) — a real regression from master, which only ever
 * indexed a template after array_key_exists() succeeded and otherwise degraded
 * quietly to the "invalid" template.
 *
 * A purpose-built fixture registry is used rather than a real brand's. Under
 * the old design that was a hard requirement — the container was a public
 * static bag with process lifetime, so mutating it here would have made the
 * suite order-dependent (the defect RSRMID-2941 fixed). It stays a fixture for
 * the reason that outlived the defect: what is under test is a translator whose
 * registry is *missing* an id every real brand ships, which no brand registry
 * can express.
 */
final class AbstractResponseTranslatorFallbackTest extends TestCase
{
    public function testMissingHttperrorTemplateDegradesToInvalidInsteadOfWarningOrThrowing(): void
    {
        $templates = new class extends AbstractResponseTemplateManager {
            /**
             * Deliberately no "httperror" key.
             * @return array<array-key, string>
             */
            #[\Override]
            protected static function builtinTemplates(): array
            {
                return [
                    "invalid" => "status=FAILURE\r\nmessage=423 Invalid API response. Contact Support\r\n",
                ];
            }

            #[\Override]
            public function generateTemplate(string $code, string $description): string
            {
                return "status=$code\r\nmessage=$description\r\n";
            }

            #[\Override]
            public function getTemplate(string $templateId): ResponseInterface
            {
                return $this->createResponse($this->hasTemplate($templateId) ? $templateId : "invalid");
            }

            #[\Override]
            protected function createResponse(string $raw): ResponseInterface
            {
                return new IBSResponse($raw, templates: $this);
            }

            #[\Override]
            protected function newResponseParser(): ResponseParserInterface
            {
                return new IBSParser();
            }

            /** @return array{0: string, 1: string} */
            #[\Override]
            protected static function matchKeys(): array
            {
                return ["status", "message"];
            }
        };

        $translator = new class extends AbstractResponseTranslator {
            /**
             * This fixture has no default registry on purpose — the one under
             * test must arrive as translate()'s argument. Throwing here (rather
             * than returning something plausible) is what makes a silent
             * fallback to a default a failure instead of a passing test against
             * the wrong registry.
             */
            #[\Override]
            protected static function newTemplateManager(): ResponseTemplateManagerInterface
            {
                throw new UnsupportedFeatureException("this fixture translator has no default registry");
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

        // A non-null $error would select "httperror" on a registry that declares
        // it; this fixture does not, so resolveTemplateId() must resolve to null
        // and fall through to hasMissingRequiredFields()/"invalid" — exactly the
        // path an ordinary unmatched $raw already takes.
        $result = $translator::translate("some raw payload", [], [], "connection refused", $templates);

        $this->assertStringContainsString("423 Invalid API response. Contact Support", $result);
        // The error never reached a template, so it must not leak into the output either.
        $this->assertStringNotContainsString("connection refused", $result);
    }
}
