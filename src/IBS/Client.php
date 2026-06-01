<?php

declare(strict_types=1);

/**
 * CNIC\IBS
 * Copyright © CentralNic Group PLC
 */

namespace CNIC\IBS;

use CNIC\CNR\Client as CNRClient;
use CNIC\CommandFormatter;
use CNIC\IBS\Logger as L;
use CNIC\IBS\Response;
use CNIC\IBS\SocketConfig;

/**
 * IBS API Client
 *
 * @package CNIC\IBS
 */
class Client extends CNRClient
{
    /**
     * Constructor
     *
     * @param string $path Path to the configuration file
     */
    public function __construct(string $path = "")
    {
        parent::__construct($path);
        $this->socketConfig = new SocketConfig($this->settings["parameters"] ?? []);
    }

    /**
     * Serialize given command for POST request including connection configuration data
     *
     * @param array<string,mixed> $cmd API command to encode
     * @param bool $secured secure password (when used for output)
     */
    #[\Override]
    public function getPOSTData(array $cmd, bool $secured = false): string
    {
        return $this->socketConfig->getPOSTData($cmd, $secured);
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
    public function setSession($value = "")
    {
        throw new \Exception("Feature `API Session` not supported.");
    }

    /**
     * Set Credentials to be used for API communication
     * Note: not supported.
     *
     * @param string $uid account name (optional, for reset)
     * @param string $role role user id (optional, for reset)
     * @param string $pw role user password (optional, for reset)
     * @throws \Exception
     */
    #[\Override]
    public function setRoleCredentials($uid = "", $role = "", $pw = "")
    {
        throw new \Exception("Feature `User Role` not supported.");
    }

    /**
     * Auto convert API command parameters to punycode, if necessary.
     *
     * @param array<string> $cmd API command
     * @return array<string>
     */
    #[\Override]
    protected function autoIDNConvert(array $cmd): array
    {
        // no IDN conversion needed
        return $cmd;
    }

    /**
     * Perform API request using the given command
     *
     * @param array<mixed> $cmd API command to request
     * @param string $path Path to the API endpoint
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
     * Set a data view to a given subuser
     * Note: not supported.
     *
     * @param string $uid subuser account name
     * @throws \Exception
     */
    #[\Override]
    public function setUserView($uid = "")
    {
        throw new \Exception("Feature `User View / Subresellersystem` not supported.");
    }

    /**
     * Activate High Performance Setup
     * Note: not supported.
     *
     * @throws \Exception
     */
    #[\Override]
    public function useHighPerformanceConnectionSetup()
    {
        throw new \Exception("Feature `High Performance Connection Setup` not supported.");
    }

    /**
     * Set default logger to use
     *
     * @return $this
     */
    #[\Override]
    public function setDefaultLogger()
    {
        $this->logger = new L();
        return $this;
    }
}
