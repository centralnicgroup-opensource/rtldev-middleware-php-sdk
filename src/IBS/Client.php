<?php

declare(strict_types=1);

/**
 * CNIC\IBS
 * Copyright © Team Internet Group PLC
 */

namespace CNIC\IBS;

use CNIC\AbstractClient;
use CNIC\CommandFormatter;
use CNIC\Exception\UnsupportedFeatureException;
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
     * Brand-mandatory cURL options. IBS/Moniker force IPv4 resolution
     * ({@see CURLOPT_IPRESOLVE}); this seeds the live {@see \CNIC\AbstractClient::$curlopts}
     * bag and is restored by {@see \CNIC\AbstractClient::resetCurlOptions()}.
     * @return array<int, mixed>
     */
    #[\Override]
    protected function getDefaultCurlOpts(): array
    {
        return [CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4];
    }

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
     * @throws UnsupportedFeatureException
     */
    #[\Override]
    public function getSession(): ?string
    {
        throw new UnsupportedFeatureException("Feature `API Session` Not supported.");
    }

    /**
     * Set an API session id to be used for API communication
     *
     * @param string $value API session id (optional, for reset)
     * @throws UnsupportedFeatureException
     */
    #[\Override]
    public function setSession(string $value = ""): static
    {
        throw new UnsupportedFeatureException("Feature `API Session` not supported.");
    }

    /**
     * Set Role Credentials to be used for API communication
     * Note: not supported.
     *
     * @throws UnsupportedFeatureException
     */
    #[\Override]
    public function setRoleCredentials(string $uid = "", string $role = "", string $pw = ""): static
    {
        throw new UnsupportedFeatureException("Feature `User Role` not supported.");
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
     * The IBS/Moniker platform exposes many endpoints under one host, where the
     * path selects the operation (e.g. `Domain/Create`, `Domain/Info`). The base
     * host is configured on the SocketConfig (`liveUrl`/`oteUrl`, host only, with
     * a trailing slash); the per-operation path is appended by
     * {@see \CNIC\AbstractClient::performRequest()} and therefore must be
     * supplied per request.
     *
     * @param array<string, scalar|scalar[]|null> $cmd API command to request
     * @param string $path Path segment appended to the base URL to select the endpoint
     */
    #[\Override]
    public function request(array $cmd = [], string $path = ""): Response
    {
        $r = $this->performRequest($cmd, $path);
        assert($r instanceof Response);
        return $r;
    }

    /**
     * Flatten the given command into wire form, injecting the JSON response format.
     * @param array<string, scalar|scalar[]|null> $cmd API command
     * @return array<string, string>
     */
    #[\Override]
    protected function buildCommand(array $cmd): array
    {
        return CommandFormatter::flattenCommand($cmd + ["ResponseFormat" => "JSON"], false);
    }

    /**
     * Instantiate an IBS Response for the given raw payload.
     * @param string $raw raw API response payload
     * @param array<string, string> $cmd flattened command that produced the response
     * @param array{CONNECTION_URL: string} $cfg connection config used for the request
     */
    #[\Override]
    protected function newResponse(string $raw, array $cmd, array $cfg): Response
    {
        return new Response($raw, $cmd, $cfg, $this->context);
    }

    /**
     * Activate High Performance Setup
     * Note: not supported.
     *
     * @throws UnsupportedFeatureException
     */
    #[\Override]
    public function useHighPerformanceConnectionSetup(): static
    {
        throw new UnsupportedFeatureException("Feature `High Performance Connection Setup` not supported.");
    }
}
