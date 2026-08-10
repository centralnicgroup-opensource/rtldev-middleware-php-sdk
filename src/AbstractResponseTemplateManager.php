<?php

declare(strict_types=1);

/**
 * CNIC
 * Copyright © Team Internet Group PLC
 */

namespace CNIC;

/**
 * Shared base for all registrar ResponseTemplateManager implementations.
 *
 * The template container plus its add/get/has/match operations are identical
 * across brands; only the built-in template strings, the generateTemplate()
 * wire format, the two hash keys used for matching, and the concrete Response /
 * ResponseParser classes differ. Concrete subclasses supply those via the
 * abstract hooks below.
 *
 * **The container is per instance** (RSRMID-2941). It used to be a
 * `public static array $templates` redeclared per brand, read live by
 * {@see AbstractResponseTranslator}, so `addTemplate()` in one test class
 * changed response translation in every later one; the `resetTemplates()` that
 * tried to contain that was a no-op unless `addTemplate()` had run first and
 * could not undo a direct write to the public property at all. Both are gone.
 * The built-ins now live in an immutable per-brand hook and are copied into
 * each new instance, so an override is scoped to the object that received it
 * and there is nothing left to reset. See {@see ResponseTemplateManagerInterface}.
 *
 * @package CNIC
 */
abstract class AbstractResponseTemplateManager implements ResponseTemplateManagerInterface
{
    /**
     * This registry's templates (template id => raw wire text), seeded from the
     * brand's built-ins and mutated only by {@see addTemplate()}.
     * @var array<array-key, string>
     */
    private array $templates;

    public function __construct()
    {
        $this->templates = static::builtinTemplates();
    }

    /**
     * The brand's built-in templates (template id => raw wire text).
     *
     * Declared as a hook over a constant rather than a property so the
     * built-ins cannot be written to: each instance gets a copy, and no route
     * exists to change what the *next* instance starts from.
     * @return array<array-key, string>
     */
    abstract protected static function builtinTemplates(): array;

    /**
     * Create a brand Response instance from a template id or raw response,
     * resolving template ids against **this** registry.
     */
    abstract protected function createResponse(string $raw): ResponseInterface;

    /**
     * Instantiate the brand's response parser.
     *
     * The template-manager twin of {@see AbstractResponse::newResponseParser()}:
     * both name the same brand parser, so the shared pipeline can parse a plain
     * response without each subclass repeating the call.
     */
    abstract protected function newResponseParser(): ResponseParserInterface;

    /**
     * The two response-hash keys this brand compares when matching a template
     * (code/description equivalent, e.g. CODE/DESCRIPTION or status/message).
     * @return array{0: string, 1: string}
     */
    abstract protected static function matchKeys(): array;

    /**
     * Register a template on this registry.
     * @param string $plain API plain response, or API response code when $description is given
     */
    #[\Override]
    public function addTemplate(string $templateId, string $plain, ?string $description = null): static
    {
        $this->templates[$templateId] = is_null($description)
            ? $plain
            : $this->generateTemplate($plain, $description);
        return $this;
    }

    /**
     * Every template in this registry as a Response, keyed by template id.
     * @return array<array-key, ResponseInterface>
     */
    #[\Override]
    public function getTemplates(): array
    {
        $tpls = [];
        foreach ($this->templates as $key => $raw) {
            $tpls[$key] = $this->createResponse($raw);
        }
        return $tpls;
    }

    /**
     * Every template in this registry as its raw wire text.
     * @return array<array-key, string>
     */
    #[\Override]
    public function getRawTemplates(): array
    {
        return $this->templates;
    }

    /**
     * Check if given template exists in this registry.
     */
    #[\Override]
    public function hasTemplate(string $templateId): bool
    {
        return array_key_exists($templateId, $this->templates);
    }

    /**
     * Check if given API response hash matches a given template by code and description
     * @param array<string, mixed> $responseHash
     */
    #[\Override]
    public function isTemplateMatchHash(array $responseHash, string $templateId): bool
    {
        return $this->matches($this->getTemplate($templateId)->getHash(), $responseHash);
    }

    /**
     * Check if given API plain response matches a given template by code and description
     * @param string $plain API plain response
     */
    #[\Override]
    public function isTemplateMatchPlain(string $plain, string $templateId): bool
    {
        // Parsed with no command on purpose: a template is not tied to one, and
        // the brand parsers that read $cmd use it only to pick their wire branch
        // (IBS: JSON when the command is empty or asks for it, plain text
        // otherwise). Templates are plain "key=value" text, which the JSON-first
        // branch reaches through its own plain-text fallback — so both routes
        // yield the same hash. Pinned by IBS's ResponseTemplateManagerTest and its
        // ResponseParserTest; keep that assertion, it is what stops the two routes
        // diverging unnoticed.
        return $this->matches($this->getTemplate($templateId)->getHash(), $this->newResponseParser()->parse($plain));
    }

    /**
     * Compare two response hashes on this brand's match keys.
     *
     * A key absent from either hash means "no match", not a warning: the
     * response being compared is arbitrary caller input (see
     * {@see isTemplateMatchHash()}), so `["status" => "SUCCESS"]` against a
     * template carrying a `message` must answer false rather than emit
     * "Undefined array key" and then compare null (RSRMID-2941).
     *
     * @param array<string, mixed> $templateHash
     * @param array<string, mixed> $responseHash
     */
    private function matches(array $templateHash, array $responseHash): bool
    {
        foreach (static::matchKeys() as $key) {
            if (!array_key_exists($key, $templateHash) || !array_key_exists($key, $responseHash)) {
                return false;
            }
            if ($templateHash[$key] !== $responseHash[$key]) {
                return false;
            }
        }
        return true;
    }
}
