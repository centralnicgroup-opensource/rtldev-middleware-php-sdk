<?php

declare(strict_types=1);

/**
 * CNIC\IBS
 * Copyright © Team Internet Group PLC
 */

namespace CNIC\IBS;

use CNIC\AbstractResponseTemplateManager;
use CNIC\IBS\ResponseParser as RP;

/**
 * IBS ResponseTemplateManager
 *
 * @psalm-api
 * @package CNIC\IBS
 * @final
 */
final class ResponseTemplateManager extends AbstractResponseTemplateManager
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
     * @param string $code API response status
     * @param string $description API response description
     */
    #[\Override]
    public static function generateTemplate(string $code, string $description): string
    {
        return "status=$code\r\nmessage=$description\r\n";
    }

    /**
     * Get response template instance from template container
     * @param string $id template id
     */
    #[\Override]
    public static function getTemplate(string $id): Response
    {
        return static::createResponse(static::hasTemplate($id) ? $id : "notfound");
    }

    /**
     * Create an IBS Response instance from a template id or raw response.
     */
    #[\Override]
    protected static function createResponse(string $raw): Response
    {
        return new Response($raw);
    }

    /**
     * Parse a plain API response into its hash form using the IBS parser.
     * @return array<string, mixed>
     */
    #[\Override]
    protected static function parseResponse(string $plain): array
    {
        return RP::parse($plain);
    }

    /**
     * IBS compares templates on the status and message hash keys.
     * @return array{0: string, 1: string}
     */
    #[\Override]
    protected static function matchKeys(): array
    {
        return ["status", "message"];
    }
}
