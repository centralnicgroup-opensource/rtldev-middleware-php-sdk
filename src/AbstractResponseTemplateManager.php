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
 * across brands; only the raw template strings, the generateTemplate() wire
 * format, the two hash keys used for matching, and the concrete Response /
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
     * Generate API response template string for given code and description
     * @param string $code API response code
     * @param string $description API response description
     */
    abstract public static function generateTemplate(string $code, string $description): string;

    /**
     * Get response template instance from template container.
     * Subclasses narrow the return type to their concrete Response.
     * @param string $id template id
     */
    abstract public static function getTemplate(string $id): ResponseInterface;

    /**
     * Create a brand Response instance from a template id or raw response.
     */
    abstract protected static function createResponse(string $raw): ResponseInterface;

    /**
     * Parse a plain API response into its hash form using the brand parser.
     * @return array<string, mixed>
     */
    abstract protected static function parseResponse(string $plain): array;

    /**
     * The two response-hash keys this brand compares when matching a template
     * (code/description equivalent, e.g. CODE/DESCRIPTION or status/message).
     * @return array{0: string, 1: string}
     */
    abstract protected static function matchKeys(): array;

    /**
     * Add response template to template container
     * @param string $id template id
     * @param string $plain API plain response or API response code (when providing $descr)
     * @param string|null $descr API response description (optional)
     */
    public static function addTemplate(string $id, string $plain, ?string $descr = null): static
    {
        static::$templates[$id] = is_null($descr) ? $plain : static::generateTemplate($plain, $descr);
        return new static();
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
     * @param string $id template id
     */
    public static function hasTemplate(string $id): bool
    {
        return array_key_exists($id, static::$templates);
    }

    /**
     * Check if given API response hash matches a given template by code and description
     * @param array<string, mixed> $tpl api response hash
     * @param string $id template id
     */
    public static function isTemplateMatchHash(array $tpl, string $id): bool
    {
        return self::matches(static::getTemplate($id)->getHash(), $tpl);
    }

    /**
     * Check if given API plain response matches a given template by code and description
     * @param string $plain API plain response
     * @param string $id template id
     */
    public static function isTemplateMatchPlain(string $plain, string $id): bool
    {
        return self::matches(static::getTemplate($id)->getHash(), static::parseResponse($plain));
    }

    /**
     * Compare two response hashes on this brand's match keys.
     * @param array<string, mixed> $h template hash
     * @param array<string, mixed> $tpl response hash to compare against
     */
    private static function matches(array $h, array $tpl): bool
    {
        [$codeKey, $descrKey] = static::matchKeys();
        return (
            ($h[$codeKey] === $tpl[$codeKey]) &&
            ($h[$descrKey] === $tpl[$descrKey])
        );
    }
}
