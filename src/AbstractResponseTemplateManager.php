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
 * The template container plus its add/get/has/reset/match operations are
 * identical across brands; only the raw template strings, the generateTemplate()
 * wire format, the two hash keys used for matching, and the concrete Response /
 * ResponseParser classes differ. Concrete subclasses supply those via the
 * abstract hooks below and redeclare their own $templates array.
 *
 * @psalm-consistent-constructor
 * @package CNIC
 */
abstract class AbstractResponseTemplateManager
{
    /**
     * Template container
     * @var array<string>
     */
    public static array $templates = [];

    /**
     * The brand's built-in templates, captured per concrete class the first time
     * that class' container is mutated, so {@see resetTemplates()} can restore
     * them. Keyed by class name because each subclass redeclares $templates and
     * therefore has a container of its own.
     * @var array<string, array<string>>
     */
    private static array $builtinTemplates = [];

    /**
     * Generate API response template string for given code and description
     */
    abstract public static function generateTemplate(string $code, string $description): string;

    /**
     * Get response template instance from template container.
     * Subclasses narrow the return type to their concrete Response.
     */
    abstract public static function getTemplate(string $templateId): ResponseInterface;

    /**
     * Create a brand Response instance from a template id or raw response.
     */
    abstract protected static function createResponse(string $raw): ResponseInterface;

    /**
     * Instantiate the brand's response parser.
     *
     * The template-manager twin of {@see AbstractResponse::newResponseParser()}:
     * both name the same brand parser, so the shared pipeline can parse a plain
     * response without each subclass repeating the call.
     */
    abstract protected static function newResponseParser(): ResponseParserInterface;

    /**
     * The two response-hash keys this brand compares when matching a template
     * (code/description equivalent, e.g. CODE/DESCRIPTION or status/message).
     * @return array{0: string, 1: string}
     */
    abstract protected static function matchKeys(): array;

    /**
     * Add response template to template container
     * @param string $plain API plain response, or API response code when $description is given
     */
    public static function addTemplate(string $templateId, string $plain, ?string $description = null): static
    {
        self::$builtinTemplates[static::class] ??= static::$templates;
        static::$templates[$templateId] = is_null($description)
            ? $plain
            : static::generateTemplate($plain, $description);
        return new static();
    }

    /**
     * Restore the brand's built-in templates, discarding everything
     * {@see addTemplate()} has registered since.
     *
     * The container is public static state with process lifetime, so a template
     * registered for one scenario stays visible to every later one — across test
     * classes in the same PHPUnit process, and in any long-lived consumer that
     * registers templates per request. Call this when a scenario ends (e.g. from
     * tearDownAfterClass()) so the next one starts from the brand defaults.
     *
     * Scope, stated rather than implied: this is the counterpart of
     * {@see addTemplate()}, not a general undo. It restores what the container
     * held the first time addTemplate() ran for *this* class, so it is per brand
     * and a no-op when the class was never added to. A direct assignment to the
     * public $templates property is outside its reach — closing that second
     * writer would mean making the property non-public, which
     * {@see \CNIC\CNR\ResponseTranslator::templates()} reads across a class
     * boundary, so it is a separate change. Go through addTemplate().
     * @psalm-api
     */
    public static function resetTemplates(): void
    {
        if (isset(self::$builtinTemplates[static::class])) {
            static::$templates = self::$builtinTemplates[static::class];
        }
    }

    /**
     * Return all available response templates
     * @return array<mixed>
     */
    public static function getTemplates(): array
    {
        $tpls = [];
        foreach (static::$templates as $key => $raw) {
            $tpls[$key] = static::createResponse($raw);
        }
        return $tpls;
    }

    /**
     * Check if given template exists in template container
     */
    public static function hasTemplate(string $templateId): bool
    {
        return array_key_exists($templateId, static::$templates);
    }

    /**
     * Check if given API response hash matches a given template by code and description
     * @param array<string, mixed> $responseHash
     */
    public static function isTemplateMatchHash(array $responseHash, string $templateId): bool
    {
        return self::matches(static::getTemplate($templateId)->getHash(), $responseHash);
    }

    /**
     * Check if given API plain response matches a given template by code and description
     * @param string $plain API plain response
     */
    public static function isTemplateMatchPlain(string $plain, string $templateId): bool
    {
        // Parsed with no command on purpose: a template is not tied to one, and
        // the brand parsers that read $cmd use it only to pick their wire branch
        // (IBS: JSON when the command is empty or asks for it, plain text
        // otherwise). Templates are plain "key=value" text, which the JSON-first
        // branch reaches through its own plain-text fallback — so both routes
        // yield the same hash. Pinned by IBS's ResponseTemplateManagerTest and its
        // ResponseParserTest; keep that assertion, it is what stops the two routes
        // diverging unnoticed.
        return self::matches(static::getTemplate($templateId)->getHash(), static::newResponseParser()->parse($plain));
    }

    /**
     * Compare two response hashes on this brand's match keys.
     * @param array<string, mixed> $templateHash
     * @param array<string, mixed> $responseHash
     */
    private static function matches(array $templateHash, array $responseHash): bool
    {
        [$codeKey, $descrKey] = static::matchKeys();
        return (
            ($templateHash[$codeKey] === $responseHash[$codeKey]) &&
            ($templateHash[$descrKey] === $responseHash[$descrKey])
        );
    }
}
