<?php

declare(strict_types=1);

/**
 * CNIC\IBS
 * Copyright © Team Internet Group PLC
 */

namespace CNIC\IBS;

use CNIC\AbstractClient;
use CNIC\CommandFormatter;
use CNIC\IBS\Logger as L;
use CNIC\IBS\Response;
use CNIC\IBS\SocketConfig;

/**
 * IBS API Client
 *
 * @package CNIC\IBS
 */
class Client extends AbstractClient
{
    /**
     * Instantiate IBS SocketConfig
     */
    #[\Override]
    protected function newSocketConfig(): SocketConfig
    {
        return new SocketConfig();
    }

    /**
     * Set default IBS logger
     * @return $this
     */
    #[\Override]
    public function setDefaultLogger(): static
    {
        $this->logger = new L();
        return $this;
    }

    /**
     * Get the API Session ID that is currently set
     * Note: not supported.
     *
     * @throws \Exception
     */
    #[\Override]
    public function getSession(): ?string
    {
        throw new \Exception("Feature `API Session` Not supported.");
    }

    /**
     * Set an API session id to be used for API communication
     *
     * @param string $value API session id (optional, for reset)
     * @throws \Exception
     */
    #[\Override]
    public function setSession(string $value = ""): static
    {
        throw new \Exception("Feature `API Session` not supported.");
    }

    /**
     * Set Role Credentials to be used for API communication
     * Note: not supported.
     *
     * @throws \Exception
     */
    #[\Override]
    public function setRoleCredentials(string $uid = "", string $role = "", string $pw = ""): static
    {
        throw new \Exception("Feature `User Role` not supported.");
    }

    /**
     * Auto convert API command parameters to punycode, if necessary.
     * Note: IBS handles IDN conversion server-side.
     *
     * @param array<string, string> $cmd API command
     * @return array<string, string>
     */
    #[\Override]
    protected function autoIDNConvert(array $cmd): array
    {
        return $cmd;
    }

    /**
     * Perform API request using the given command.
     *
     * Unlike CNR — which targets a single fixed endpoint baked into the
     * configured URL — the IBS/Moniker platform exposes many endpoints under one
     * host, where the path selects the operation (e.g. `Domain/Create`,
     * `Domain/Info`). The base host is configured on the SocketConfig
     * (`liveUrl`/`oteUrl`, host only, with a trailing slash); the per-operation
     * path is appended here and therefore must be supplied per request.
     *
     * This is why the signature widens {@see \CNIC\AbstractClient::request()}
     * with an optional `$path`: it is deliberate and accepted by both static
     * analysers. Consumers that need `$path` must hold the concrete
     * IBS/Moniker `Client` type — the abstract contract intentionally omits it.
     *
     * @param array<string, scalar|scalar[]|null> $cmd API command to request
     * @param string $path Path segment appended to the base URL to select the endpoint
     */
    #[\Override]
    public function request(array $cmd = [], string $path = ""): Response
    {
        $mycmd = CommandFormatter::flattenCommand($cmd + ["ResponseFormat" => "JSON"], false);
        $mycmd = $this->autoIDNConvert($mycmd);
        $cfg = ["CONNECTION_URL" => $this->socketURL . $path];
        $data = $this->getPOSTData($mycmd);
        [$raw, $error] = $this->executeCurl($data, $cfg, [CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4]);
        $response = new Response($raw, $mycmd, $cfg, $this->context);
        if ($this->debugMode) {
            $this->logger->log($this->getPOSTData($mycmd, true), $response, $error);
        }
        return $response;
    }

    /**
     * Activate High Performance Setup
     * Note: not supported.
     *
     * @throws \Exception
     */
    #[\Override]
    public function useHighPerformanceConnectionSetup(): static
    {
        throw new \Exception("Feature `High Performance Connection Setup` not supported.");
    }
}
