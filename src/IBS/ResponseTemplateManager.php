<?php

declare(strict_types=1);

/**
 * CNIC\IBS
 * Copyright © CentralNic Group PLC
 */

namespace CNIC\IBS;

use CNIC\IBS\ResponseParser as RP;

/**
 * IBS ResponseTemplateManager
 *
 * @psalm-api
 * @package CNIC\IBS
 * @final
 */
final class ResponseTemplateManager
{
    /**
     * template container
     * @var array<string>
     */
    public static array $templates = [
        "403" => "status=FAILURE\r\nmessage=403 Forbidden\r\n",
        "404" => "status=FAILURE\r\nmessage=421 Page not found\r\n",
        "500" => "status=FAILURE\r\nmessage=500 Internal server error\r\n",
        "empty" => "status=FAILURE\r\nmessage=423 Empty API response. Probably unreachable API end point {CONNECTION_URL}\r\n",
        "error" => "status=FAILURE\r\nmessage=421 Command failed due to server error. Please retry.\r\n",
        "httperror" => "status=FAILURE\r\nmessage=421 Command failed due to HTTP communication error{HTTPERROR}.\r\n",
        "invalid" => "status=FAILURE\r\nmessage=423 Invalid API response. Contact Support\r\n",
        "nocurl" => "status=FAILURE\r\nmessage=423 API access error: curl_init failed\r\n",
        "notfound" => "status=FAILURE\r\nmessage=500 Response Template not found\r\n",
        "unauthorized" => "status=FAILURE\r\nmessage=530 Unauthorized\r\n"
    ];

    /**
     * Generate API response template string for given status and description
     * @param string $status API response code
     * @param string $description API response description
     */
    public static function generateTemplate(string $status, string $description): string
    {
        return "status=$status\r\nmessage=$description\r\n";
    }

    /**
     * Add response template to template container
     * @param string $id template id
     * @param string $plain API plain response or API response code (when providing $descr)
     * @param string|null $descr API response description
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
     * @param array<string, mixed> $tpl api response hash
     * @param string $id template id
     */
    public static function isTemplateMatchHash(array $tpl, string $id): bool
    {
        $h = self::getTemplate($id)->getHash();
        return (
            ($h["status"] === $tpl["status"]) &&
            ($h["message"] === $tpl["message"])
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
            ($h["status"] === $tpl["status"]) &&
            ($h["message"] === $tpl["message"])
        );
    }
}
