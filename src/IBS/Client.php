<?php

#declare(strict_types=1);

/**
 * CNIC\IBS
 * Copyright © CentralNic Group PLC
 */

namespace CNIC\IBS;

use CNIC\CommandFormatter;
use CNIC\IBS\Logger as L;
use CNIC\IBS\SocketConfig;
use CNIC\IBS\Response;

/**
 * IBS API Client
 *
 * @package CNIC\IBS
 */
class Client extends \CNIC\CNR\Client
{
    /**
     * Object covering API connection data
     * @var SocketConfig
     */
    protected $socketConfig;

    /**
     * Constructor
     *
     * @param string $path Path to the configuration file
     */
    public function __construct($path = "")
    {
        $contents = file_get_contents($path) ?: "";
        $settings = json_decode($contents, true);
        if (is_null($settings) || $settings === false || $settings === true) {
            $settings = [];
        }
        $this->settings = $settings;
        $this->socketURL = "";
        $this->debugMode = false;
        $this->ua = "";
        $this->socketConfig = new SocketConfig($this->settings["parameters"]);
        $this->useLIVESystem();
        $this->setDefaultLogger();
    }

    /**
     * Serialize given command for POST request including connection configuration data
     *
     * @param array<int|string,mixed> $cmd API command to encode
     * @param bool $secured secure password (when used for output)
     * @return string
     */
    public function getPOSTData($cmd, $secured = false)
    {
        return $this->socketConfig->getPOSTData($cmd, $secured);
    }

    /**
     * Get the API Session ID that is currently set
     * Note: not supported.
     *
     * @throws \Exception
     */
    public function getSession()
    {
        throw new \Exception("Feature `API Session` Not supported.");
    }

    /**
     * Set an API session id to be used for API communication
     *
     * @param string $value API session id (optional, for reset)
     * @throws \Exception
     */
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
    protected function autoIDNConvert($cmd)
    {
        // no IDN conversion needed
        return $cmd;
    }

    /**
     * Perform API request using the given command
     *
     * @param array<mixed> $cmd API command to request
     * @param string $path Path to the API endpoint
     * @return Response
     */
    public function request(array $cmd = [], $path = "")
    {
        // flatten nested api command bulk parameters and sort them
        $mycmd = CommandFormatter::flattenCommand($cmd + ["ResponseFormat" => "JSON"], false);
        // auto convert umlaut names to punycode
        $mycmd = $this->autoIDNConvert($mycmd);
        // request command to API
        $cfg = [
            "CONNECTION_URL" => $this->socketURL . $path
        ];
        $data = $this->getPOSTData($mycmd);

        if (!$this->chandle) {
            $tmp = curl_init();
            if ($tmp === false) {
                $r = new Response("nocurl", $mycmd, $cfg);
                if ($this->debugMode) {
                    $secured = $this->getPOSTData($mycmd, true);
                    $this->logger->log($secured, $r, "CURL for PHP missing.");
                }
                return $r;
            }
            $this->chandle = $tmp;
        }

        curl_setopt_array($this->chandle, [
            // CURLOPT_VERBOSE         => $this->debugMode,
            CURLOPT_URL             => $cfg["CONNECTION_URL"],
            CURLOPT_CONNECTTIMEOUT  => 30, // 30s, 300s by default
            CURLOPT_TIMEOUT         => $this->settings["socketTimeout"],
            CURLOPT_POST            => 1,
            CURLOPT_HEADER          => 0,
            CURLOPT_RETURNTRANSFER  => 1,
            CURLOPT_POSTFIELDS      => $data,
            CURLOPT_USERAGENT       => $this->getUserAgent(),
            CURLOPT_HTTPHEADER      => [
                "Expect:",
                "Content-Type: application/x-www-form-urlencoded", //UTF-8 implied
                "Content-Length: " . strlen($data),
                "Connection: keep-alive"
            ],
            CURLOPT_SSL_VERIFYPEER => 0, // IBS / Moniker only
            CURLOPT_SSL_VERIFYHOST => 0, // IBS / Moniker only
            CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4 // IBS / Moniker only
        ] + $this->curlopts);

        // which is by default tested for by phpStan
        /** @var string|false $r */
        $r = curl_exec($this->chandle);
        $error = null;
        if ($r === false) {
            $error = curl_error($this->chandle);
            $r = "httperror|" . $error;
        }
        $response = new Response($r, $mycmd, $cfg);

        if ($this->debugMode) {
            $secured = $this->getPOSTData($mycmd, false);
            $this->logger->log($secured, $response, $error);
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
    public function useHighPerformanceConnectionSetup()
    {
        throw new \Exception("Feature `High Performance Connection Setup` not supported.");
    }

    /**
     * Set default logger to use
     *
     * @return $this
     */
    public function setDefaultLogger()
    {
        $this->logger = new L();
        return $this;
    }
}
