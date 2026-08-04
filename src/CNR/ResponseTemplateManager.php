<?php

declare(strict_types=1);

/**
 * CNIC\CNR
 * Copyright © Team Internet Group PLC
 */

namespace CNIC\CNR;

use CNIC\AbstractResponseTemplateManager;
use CNIC\CNR\ResponseParser as RP;
use CNIC\ResponseParserInterface;

/**
 * CNR ResponseTemplateManager
 *
 * @psalm-api
 * @package CNIC\CNR
 * @final
 */
final class ResponseTemplateManager extends AbstractResponseTemplateManager
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
        "notfound" => "[RESPONSE]\r\nCODE=500\r\nDESCRIPTION=Response Template not found\r\nEOF\r\n",
        "unauthorized" => "[RESPONSE]\r\nCODE=530\r\nDESCRIPTION=Unauthorized\r\nEOF\r\n"
    ];

    /**
     * Generate API response template string for given code and description
     */
    #[\Override]
    public static function generateTemplate(string $code, string $description): string
    {
        return "[RESPONSE]\r\nCODE=" . $code . "\r\nDESCRIPTION=" . $description . "\r\nEOF\r\n";
    }

    /**
     * Get response template instance from template container
     */
    #[\Override]
    public static function getTemplate(string $templateId): Response
    {
        return self::createResponse(self::hasTemplate($templateId) ? $templateId : "notfound");
    }

    /**
     * Create a CNR Response instance from a template id or raw response.
     */
    #[\Override]
    protected static function createResponse(string $raw): Response
    {
        return new Response($raw);
    }

    /**
     * Instantiate the CNR response parser.
     */
    #[\Override]
    protected static function newResponseParser(): ResponseParserInterface
    {
        return new RP();
    }

    /**
     * CNR compares templates on the CODE and DESCRIPTION hash keys.
     * @return array{0: string, 1: string}
     */
    #[\Override]
    protected static function matchKeys(): array
    {
        return ["CODE", "DESCRIPTION"];
    }
}
