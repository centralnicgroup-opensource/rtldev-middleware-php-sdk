<?php

declare(strict_types=1);

/**
 * CNIC
 * Copyright © Team Internet Group PLC
 */

namespace CNIC;

/**
 * The **opt-in** half of a template registry: turning stored templates into
 * brand {@see ResponseInterface} objects, and comparing a response against
 * one.
 *
 * Separate from {@see ResponseTemplateManagerInterface} (RSRMID-2968) because
 * the two roles have different consumers: the pipeline needs the registry and
 * must *not* be able to reach this one, since every method here builds a
 * Response that re-runs `translate()`/parse/column assembly.
 *
 * {@see AbstractResponseTemplateManager} implements both faces on one object —
 * a brand Response can only be built by a brand-aware class, so a standalone
 * factory would mean a second parallel brand hierarchy for no gain.
 * Consumers opt in by *typing* against the face they need.
 *
 * Every method here resolves a **template id** against the registry, never
 * wire text (see
 * {@see AbstractResponseTemplateManager::createResponseFromTemplateId()}).
 *
 * @psalm-api
 * @package CNIC
 */
interface ResponseTemplateFactoryInterface
{
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
