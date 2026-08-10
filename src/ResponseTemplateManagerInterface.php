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
 * The seam has two production-relevant shapes, which is what justifies it being
 * an interface rather than a concrete class: a Response built with no registry
 * gets the brand's built-ins, and one built with a registry gets exactly what
 * that object holds. See {@see AbstractResponse::__construct()}'s $templates
 * argument.
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
     * The Response for the given template id, or the brand's "notfound"
     * template when this registry holds no such id.
     *
     * The Response is built against **this** registry, so a template registered
     * here resolves here and nowhere else.
     */
    public function getTemplate(string $templateId): ResponseInterface;

    /**
     * Every template in this registry as a Response, keyed by template id.
     * @return array<array-key, ResponseInterface>
     */
    public function getTemplates(): array;

    /**
     * Every template in this registry as its raw wire text, keyed by template
     * id — the snapshot {@see AbstractResponseTranslator::translate()} resolves
     * ids against.
     *
     * Distinct from {@see getTemplates()} on purpose: the translator needs the
     * strings, and building a Response per entry to translate one response
     * would recurse.
     * @return array<array-key, string>
     */
    public function getRawTemplates(): array;

    /**
     * Whether the given API response hash matches a template held here, on this
     * brand's two match keys (CNR: CODE/DESCRIPTION, IBS: status/message).
     * @param array<string, mixed> $responseHash
     */
    public function isTemplateMatchHash(array $responseHash, string $templateId): bool;

    /**
     * Whether the given API plain response matches a template held here, on
     * this brand's two match keys.
     * @param string $plain API plain response
     */
    public function isTemplateMatchPlain(string $plain, string $templateId): bool;
}
