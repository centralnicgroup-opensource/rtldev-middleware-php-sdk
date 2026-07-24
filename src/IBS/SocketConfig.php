<?php

declare(strict_types=1);

/**
 * CNIC\IBS
 * Copyright © Team Internet Group PLC
 */

namespace CNIC\IBS;

use CNIC\AbstractSocketConfig;

/**
 * IBS SocketConfig
 *
 * @package CNIC\IBS
 */
class SocketConfig extends AbstractSocketConfig
{
    protected string $oteUrl = "https://testapi.internet.bs/";
    protected string $liveUrl = "https://api.internet.bs/";
    protected int $socketTimeout = 300;
    // IBS/Moniker convert IDNs server-side; this false flag makes the shared
    // AbstractClient::autoIDNConvert() short-circuit and pass the command through
    // verbatim, so no brand-level override is needed.
    protected bool $needsIDNConvert = false;

    /**
     * IBS carries sensitive data under lower-/camel-case command keys. Mirrors
     * {@see \CNIC\IBS\Response::$sensitiveFields}.
     * @var string[]
     */
    protected array $sensitiveFields = ["password", "transferAuthInfo"];

    /**
     * list of http request parameters
     * IBS only uses login/password — command and session are CNR-specific.
     * @var array{login: string, password: string}
     */
    private array $parameters = [
        "login"    => "apikey",
        "password" => "password",
    ];

    /**
     * Get POST data container of connection data
     * @param array<string, string|null> $command API Command to request
     * @param bool $secured if password has to be returned "hidden"
     * @return array<string, string|null>
     */
    #[\Override]
    protected function getPOSTDataParams(array $command, bool $secured): array
    {
        $params = $secured ? $this->maskSensitiveCommand($command) : $command;
        if (strlen($this->login) !== 0) {
            $params[$this->parameters["login"]] = $this->login;
        }
        if (strlen($this->pw) !== 0) {
            $params[$this->parameters["password"]] = $secured ? "***" : $this->pw;
        }
        return $params;
    }
}
