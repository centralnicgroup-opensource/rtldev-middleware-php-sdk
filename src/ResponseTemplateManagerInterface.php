<?php

declare(strict_types=1);

/**
 * CNIC
 * Copyright © Team Internet Group PLC
 */

namespace CNIC;

/**
 * Contract for a brand's response-template registry.
 *
 * A template is a canned raw API response — the brand's own built-ins ("empty",
 * "invalid", "httperror", …) plus whatever a caller registers on top. The
 * registry is what {@see AbstractResponseTranslator::translate()} looks a raw
 * payload up in: a payload equal to a known template id resolves to that
 * template's wire text, which is the sanctioned way to exercise a specific
 * canned response with no API round-trip.
 *
 * **Every implementation must be an instance, and every instance owns its own
 * templates** (RSRMID-2941). Until then the registry was a `public static`
 * array with process lifetime, so registering a template in one test class
 * changed response translation in every later one and the reset path could not
 * reliably undo it. Handing the registry to the Response that needs it, rather
 * than mutating a container the whole process shares, is what makes the
 * override scoped — do not reintroduce a static container, a singleton, or a
 * `reset()` that only exists because the state outlives its user.
 *
 * This is the **pipeline contract**: exactly what
 * {@see AbstractResponseTranslator::translate()} and
 * {@see AbstractResponse::__construct()}'s `$templates` argument consume, and
 * nothing more. The single production call site is {@see getRawTemplates()}.
 *
 * **It is deliberately not a polymorphism seam** (RSRMID-2968). It has one
 * direct implementer, {@see AbstractResponseTemplateManager}; both brand
 * classes and the one test double inherit that. It is kept as an interface
 * because it is the *narrowing point* — it is what lets the pipeline hold a
 * registry without being able to reach Response production — and because it
 * documents the contract a third-party brand must satisfy. Do not justify it
 * by claiming adapter-substitutability it does not have.
 *
 * **Nothing that produces a {@see ResponseInterface} may be declared here.**
 * Response production lives on {@see ResponseTemplateFactoryInterface}, which
 * the pipeline never types against. Merging the two back would put
 * `translate()` within reach of `getTemplate()`, whose Response re-enters
 * `translate()` — the recursion that
 * {@see AbstractResponseTranslator::translate()} currently avoids only by a
 * hand-written comment. Pinned by `tests/ResponseTemplateFactorySeamTest.php`.
 *
 * @psalm-api
 * @package CNIC
 */
interface ResponseTemplateManagerInterface
{
    /**
     * Build this brand's wire-format template string for a response code and
     * its human-readable text (CNR: `[RESPONSE]…CODE=…DESCRIPTION=…EOF`, IBS:
     * `status=…message=…`).
     */
    public function generateTemplate(string $code, string $description): string;

    /**
     * Register a template on **this** registry, replacing any entry under the
     * same id, and return $this so registrations chain.
     *
     * Fluent per the project's setter convention — and, unlike the static
     * predecessor that returned a throwaway `new static()`, the returned object
     * is genuinely the one that received the template.
     *
     * @param string $plain API plain response, or API response code when $description is given
     */
    public function addTemplate(string $templateId, string $plain, ?string $description = null): static;

    /**
     * Whether this registry holds a template under the given id.
     */
    public function hasTemplate(string $templateId): bool;

    /**
     * Every template in this registry as its raw wire text, keyed by template
     * id — the snapshot {@see AbstractResponseTranslator::translate()} resolves
     * ids against.
     *
     * Distinct from {@see ResponseTemplateFactoryInterface::getTemplates()} on
     * purpose: the translator needs the strings, and building a Response per
     * entry to translate one response would recurse.
     * @return array<array-key, string>
     */
    public function getRawTemplates(): array;
}
