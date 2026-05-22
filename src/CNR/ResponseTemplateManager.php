<?php

declare(strict_types=1);

/**
 * CNIC\CNR
 * Copyright © CentralNic Group PLC
 */

namespace CNIC\CNR;

use CNIC\CNR\ResponseParser as RP;

/**
 * CNR ResponseTemplateManager
 *
 * @psalm-api
 * @package CNIC\CNR
 * @final
 */
final class ResponseTemplateManager
{
    /**
     * Template container
     * @var array<string>
     */
    public static array $templates = [
        "404" => "[RESPONSE]\r\nCODE=421\r\nDESCRIPTION=Page not found\r\nEOF\r\n",
        "500" => "[RESPONSE]\r\nCODE=500\r\nDESCRIPTION=Internal server error\r\nEOF\r\n",
        "empty" => "[RESPONSE]\r\nCODE=423\r\nDESCRIPTION=Empty API response. Probably unreachable API end point {CONNECTION_URL}\r\nEOF\r\n",
        "error" => "[RESPONSE]\r\nCODE=421\r\nDESCRIPTION=Command failed due to server error. Client should try again\r\nEOF\r\n",
        "expired" => "[RESPONSE]\r\nCODE=530\r\nDESCRIPTION=SESSION NOT FOUND\r\nEOF\r\n",
        "httperror" => "[RESPONSE]\r\nCODE=421\r\nDESCRIPTION=Command failed due to HTTP communication error{HTTPERROR}.\r\nEOF\r\n",
        "invalid" => "[RESPONSE]\r\nCODE=423\r\nDESCRIPTION=Invalid API response. Contact Support\r\nEOF\r\n",
        "nocurl" => "[RESPONSE]\r\nCODE=423\r\nDESCRIPTION=API access error: curl_init failed\r\nEOF\r\n",
        "notfound" => "[RESPONSE]\r\nCODE=500\r\nDESCRIPTION=Response Template not found\r\nEOF\r\n",
        "unauthorized" => "[RESPONSE]\r\nCODE=530\r\nDESCRIPTION=Unauthorized\r\nEOF\r\n"
    ];

    /**
     * Generate API response template string for given code and description
     * @param string $code API response code
     * @param string $description API response description
     */
    public static function generateTemplate(string $code, string $description): string
    {
        return "[RESPONSE]\r\nCODE=" . $code . "\r\nDESCRIPTION=" . $description . "\r\nEOF\r\n";
    }

    /**
     * Add response template to template container
     * @param string $id template id
     * @param string $plain API plain response or API response code (when providing $descr)
     * @param string|null $descr API response description (optional)
     */
    public static function addTemplate(string $id, string $plain, ?string $descr = null): self
    {
        self::$templates[$id] = is_null($descr) ? $plain : self::generateTemplate($plain, $descr);
        return new self();
    }

    /**
     * Get response template instance from template container
     * @param string $id template id
     */
    public static function getTemplate(string $id): Response
    {
        if (self::hasTemplate($id)) {
            return new Response($id);
        }
        return new Response("notfound");
    }

    /**
     * Return all available response templates
     * @return array<mixed>
     */
    public static function getTemplates(): array
    {
        $tpls = [];
        foreach (self::$templates as $key => $raw) {
            $tpls[$key] = new Response($raw);
        }
        return $tpls;
    }

    /**
     * Check if given template exists in template container
     * @param string $id template id
     */
    public static function hasTemplate(string $id): bool
    {
        return array_key_exists($id, self::$templates);
    }

    /**
     * Check if given API response hash matches a given template by code and description
     * @param array<string> $tpl api response hash
     * @param string $id template id
     */
    public static function isTemplateMatchHash(array $tpl, string $id): bool
    {
        $h = self::getTemplate($id)->getHash();
        return (
            ($h["CODE"] === $tpl["CODE"]) &&
            ($h["DESCRIPTION"] === $tpl["DESCRIPTION"])
        );
    }

    /**
     * Check if given API plain response matches a given template by code and description
     * @param string $plain API plain response
     * @param string $id template id
     */
    public static function isTemplateMatchPlain(string $plain, string $id): bool
    {
        $h = self::getTemplate($id)->getHash();
        $tpl = RP::parse($plain);
        return (
            ($h["CODE"] === $tpl["CODE"]) &&
            ($h["DESCRIPTION"] === $tpl["DESCRIPTION"])
        );
    }
}
