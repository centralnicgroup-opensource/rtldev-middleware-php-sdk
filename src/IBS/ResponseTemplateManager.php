<?php

declare(strict_types=1);

/**
 * CNIC\IBS
 * Copyright © Team Internet Group PLC
 */

namespace CNIC\IBS;

use CNIC\AbstractResponseTemplateManager;
use CNIC\IBS\ResponseParser as RP;
use CNIC\ResponseParserInterface;

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
     * IBS's built-in templates. A constant, not a property: each registry
     * instance copies these at construction and mutates only its copy, so there
     * is no route by which one caller's override reaches another (RSRMID-2941).
     * @var array<array-key, string>
     */
    private const array BUILTIN_TEMPLATES = [
        "403" => "status=FAILURE\r\nmessage=403 Forbidden\r\n",
        "404" => "status=FAILURE\r\nmessage=421 Page not found\r\n",
        "500" => "status=FAILURE\r\nmessage=500 Internal server error\r\n",
        "empty" => "status=FAILURE\r\nmessage=423 Empty API response. Probably unreachable API end point {CONNECTION_URL}\r\n",
        "error" => "status=FAILURE\r\nmessage=421 Command failed due to server error. Please retry.\r\n",
        "httperror" => "status=FAILURE\r\nmessage=421 Command failed due to HTTP communication error{HTTPERROR}.\r\n",
        "invalid" => "status=FAILURE\r\nmessage=423 Invalid API response. Contact Support\r\n",
        "notfound" => "status=FAILURE\r\nmessage=500 Response Template not found\r\n",
        "unauthorized" => "status=FAILURE\r\nmessage=530 Unauthorized\r\n"
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
     * Generate API response template string for given status and description
     * @param string $code goes on the wire as IBS's `status` field
     */
    #[\Override]
    public function generateTemplate(string $code, string $description): string
    {
        return "status=$code\r\nmessage=$description\r\n";
    }

    /**
     * Get response template instance from this registry
     */
    #[\Override]
    public function getTemplate(string $templateId): Response
    {
        return $this->createResponseFromTemplateId($this->hasTemplate($templateId) ? $templateId : "notfound");
    }

    /**
     * Create an IBS Response instance from a template id.
     *
     * The registry is handed to the Response so the template id resolves
     * against *this* object — that hand-off is what replaced the global
     * lookup.
     */
    #[\Override]
    protected function createResponseFromTemplateId(string $templateId): Response
    {
        return new Response($templateId, templates: $this);
    }

    /**
     * Instantiate the IBS response parser.
     */
    #[\Override]
    protected function newResponseParser(): ResponseParserInterface
    {
        return new RP();
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
