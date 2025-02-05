<?php

#declare(strict_types=1);

/**
 * CNIC\IBS
 * Copyright © CentralNic Group PLC
 */

namespace CNIC\IBS;

use CNIC\IBS\ResponseParser as RP;

/**
 * IBS ResponseTemplateManager
 *
 * @package CNIC\IBS
 */

final class ResponseTemplateManager
{
    /**
     * template container
     * @var array<string>
     */
    public static $templates = [
        "403" => self::generateTemplate("FAILURE", "403 Forbidden"),
        "404" => self::generateTemplate("FAILURE", "421 Page not found"),
        "500" => self::generateTemplate("FAILURE", "500 Internal server error"),
        "empty" => self::generateTemplate("FAILURE", "423 Empty API response. Probably unreachable API end point {CONNECTION_URL}"),
        "error" => self::generateTemplate("FAILURE", "421 Command failed due to server error. Please retry."),
        "httperror" => self::generateTemplate("FAILURE", "421 Command failed due to HTTP communication error{HTTPERROR}."),
        "invalid" => self::generateTemplate("FAILURE", "423 Invalid API response. Contact Support"),
        "nocurl" => self::generateTemplate("FAILURE", "423 API access error: curl_init failed"),
        "notfound" => self::generateTemplate("FAILURE", "500 Response Template not found"),
        "unauthorized" => self::generateTemplate("FAILURE", "530 Unauthorized")
    ];

    /**
     * Generate API response template string for given status and description
     * @param string $status API response code
     * @param string $description API response description
     * @return string generate response template string
     */
    public static function generateTemplate($status, $description)
    {
        return "status=$status\r\nmessage=$description\r\n";
    }

    /**
     * Add response template to template container
     * @param string $id template id
     * @param string $plain API plain response or API response code (when providing $descr)
     * @param string|null $descr API response description
     * @return self
     */
    public static function addTemplate($id, $plain, $descr = null)
    {
        if (is_null($descr)) {
            self::$templates[$id] = $plain;
        } else {
            self::$templates[$id] = self::generateTemplate($plain, $descr);
        }
        return new self();
    }

    /**
     * Get response template instance from template container
     * @param string $id template id
     * @return Response template instance
     */
    public static function getTemplate($id)
    {
        if (self::hasTemplate($id)) {
            return new Response($id);
        }
        return new Response("notfound");
    }

    /**
     * Return all available response templates
     * @return array<mixed> all available response instances
     */
    public static function getTemplates()
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
     * @return bool boolean result
     */
    public static function hasTemplate($id)
    {
        return array_key_exists($id, self::$templates);
    }

    /**
     * Check if given API response hash matches a given template by code and description
     * @param array<string> $tpl api response hash
     * @param string $id template id
     * @return bool boolean result
     */
    public static function isTemplateMatchHash($tpl, $id)
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
     * @return bool boolean result
     */
    public static function isTemplateMatchPlain($plain, $id)
    {
        $h = self::getTemplate($id)->getHash();
        $tpl = RP::parse($plain);
        return (
            ($h["status"] === $tpl["status"]) &&
            ($h["message"] === $tpl["message"])
        );
    }
}
