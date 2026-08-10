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
     * CNR's built-in templates. A constant, not a property: each registry
     * instance copies these at construction and mutates only its copy, so there
     * is no route by which one caller's override reaches another (RSRMID-2941).
     * @var array<array-key, string>
     */
    private const array BUILTIN_TEMPLATES = [
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
     * @return array<array-key, string>
     */
    #[\Override]
    protected static function builtinTemplates(): array
    {
        return self::BUILTIN_TEMPLATES;
    }

    /**
     * Generate API response template string for given code and description
     */
    #[\Override]
    public function generateTemplate(string $code, string $description): string
    {
        return "[RESPONSE]\r\nCODE=" . $code . "\r\nDESCRIPTION=" . $description . "\r\nEOF\r\n";
    }

    /**
     * Get response template instance from this registry
     */
    #[\Override]
    public function getTemplate(string $templateId): Response
    {
        return $this->createResponse($this->hasTemplate($templateId) ? $templateId : "notfound");
    }

    /**
     * Create a CNR Response instance from a template id or raw response.
     *
     * The registry is handed to the Response so a template id resolves against
     * *this* object — that hand-off is what replaced the global lookup.
     */
    #[\Override]
    protected function createResponse(string $raw): Response
    {
        return new Response($raw, templates: $this);
    }

    /**
     * Instantiate the CNR response parser.
     */
    #[\Override]
    protected function newResponseParser(): ResponseParserInterface
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
