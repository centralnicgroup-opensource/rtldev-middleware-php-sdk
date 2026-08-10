<?php

declare(strict_types=1);

/**
 * CNIC\IBS
 * Copyright © Team Internet Group PLC
 */

namespace CNIC\IBS;

use CNIC\AbstractSocketConfig;
use CNIC\CommandRedactor;

/**
 * IBS SocketConfig
 *
 * @package CNIC\IBS
 */
class SocketConfig extends AbstractSocketConfig
{
    protected string $oteUrl = "https://testapi.internet.bs/";
    protected string $liveUrl = "https://api.internet.bs/";

    /**
     * IBS carries sensitive data under lower-/camel-case command keys. Declared
     * once in {@see SensitiveFields::KEYS}, shared with {@see \CNIC\IBS\Response}.
     * @var string[]
     */
    protected array $sensitiveFields = SensitiveFields::KEYS;

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
     * @return array<string, string|null>
     */
    #[\Override]
    protected function getPOSTDataParams(array $command, bool $maskSecrets): array
    {
        $params = $maskSecrets ? $this->maskSensitiveCommand($command) : $command;
        if (strlen($this->login) !== 0) {
            $params[$this->parameters["login"]] = $this->login;
        }
        if (strlen($this->password) !== 0) {
            $params[$this->parameters["password"]] = $maskSecrets ? CommandRedactor::MASK : $this->password;
        }
        return $params;
    }
}
