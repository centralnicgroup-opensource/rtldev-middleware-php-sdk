<?php

#declare(strict_types=1);

/**
 * CNIC\CNR
 * Copyright © CentralNic Group PLC
 */

namespace CNIC\CNR;

use CNIC\CNR\Logger as L;
use CNIC\CNR\SocketConfig;
use CNIC\CNR\Response;
use CNIC\CommandFormatter;

/**
 * CNR API Client
 *
 * @package CNIC\CNR
 */

class Client extends \CNIC\HEXONET\Client
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
        /** @var array<mixed> $settings */
        $settings = json_decode($contents, true);
        $this->settings = $settings;
        $this->socketURL = "";
        $this->debugMode = false;
        $this->ua = "";
        $this->socketConfig = new SocketConfig($this->settings["parameters"]);
        $this->useLIVESystem();
        $this->setDefaultLogger();
    }

    /**
     * Perform API request using the given command
     * @param array<mixed> $cmd API command to request
     * @return Response Response
     */
    public function request($cmd = [])
    {
        $mycmd = [];
        if (!empty($cmd)) {
            // flatten nested api command bulk parameters and sort them
            $mycmd = CommandFormatter::flattenCommand($cmd);
            // auto convert umlaut names to punycode
            $mycmd = $this->autoIDNConvert($mycmd);
        }
        // request command to API
        $cfg = [
            "CONNECTION_URL" => $this->socketURL
        ];
        $data = $this->getPOSTData($mycmd);
        $curl = curl_init($cfg["CONNECTION_URL"]);
        // PHP 7.3 return false vs. 7.4 throws an Exception
        // when setting the URL to "\0"
        // @codeCoverageIgnoreStart
        if ($curl === false) {
            $r = new Response("nocurl", $mycmd, $cfg);
            if ($this->debugMode) {
                $secured = $this->getPOSTData($mycmd, true);
                $this->logger->log($secured, $r, "CURL for PHP missing.");
            }
            return $r;
        }
        // @codeCoverageIgnoreEnd
        curl_setopt_array($curl, [
            // CURLOPT_VERBOSE         => $this->debugMode,
            CURLOPT_CONNECTTIMEOUT  => 5, // 5s
            CURLOPT_TIMEOUT         => $this->settings["socketTimeout"],
            CURLOPT_POST            => 1,
            CURLOPT_POSTFIELDS      => $data,
            CURLOPT_HEADER          => 0,
            CURLOPT_RETURNTRANSFER  => 1,
            CURLOPT_USERAGENT       => $this->getUserAgent(),
            CURLOPT_HTTPHEADER      => [
                "Expect:",
                "Content-Type: application/x-www-form-urlencoded", //UTF-8 implied
                "Content-Length: " . strlen($data)
            ]
        ] + $this->curlopts);

        // which is by default tested for by phpStan
        /** @var string|false $r */
        $r = curl_exec($curl);
        $error = null;
        if ($r === false) {
            $error = curl_error($curl);
            $r = "httperror|" . $error;
        }
        $response = new Response($r, $mycmd, $cfg);

        curl_close($curl);
        unset($curl); // https://php.watch/versions/8.0/resource-CurlHandle
        if ($this->debugMode) {
            $secured = $this->getPOSTData($mycmd, true);
            $this->logger->log($secured, $response, $error);
        }
        return $response;
    }

    /**
     * set default logger to use
     * @return $this
     */
    public function setDefaultLogger()
    {
        $this->logger = new L();
        return $this;
    }

    /**
     * Auto convert API command parameters to punycode, if necessary.
     * @param array<string> $cmd API command
     * @return array<string>
     */
    protected function autoIDNConvert($cmd)
    {
        // only convert if configured for the registrar
        // and ignore commands in string format (even deprecated)
        if (
            !$this->settings["needsIDNConvert"]
            || !function_exists("idn_to_ascii")
        ) {
            return $cmd;
        }

        $asciipattern = "/^[a-zA-Z0-9\.-]+$/i";
        // DOMAIN params get auto-converted by API
        // RSRBE-7149 for NS coverage
        $keypattern = "/^(NAMESERVER|NS|DNSZONE)([0-9]*)$/i";
        $objclasspattern = "/^(DOMAIN(APPLICATION|BLOCKING)?|NAMESERVER|NS|DNSZONE)$/i";
        $toconvert = [];
        $idxs = [];
        foreach ($cmd as $key => $val) {
            if (
                ((bool)preg_match($keypattern, $key)
                    // RSRTPM-3167: OBJECTID is a PATTERN in CNR API and not supporting IDNs
                    || ($key === "OBJECTID"
                        && isset($cmd["OBJECTCLASS"])
                        && (bool)preg_match($objclasspattern, $cmd["OBJECTCLASS"])
                    )
                )
                && !(bool)preg_match($asciipattern, $val)
            ) {
                $toconvert[] = $val;
                $idxs[] = $key;
            }
        }
        if (!empty($toconvert)) {
            $results = $this->IDNConvert($toconvert);
            foreach ($results as $idx => $row) {
                $cmd[$idxs[$idx]] = $row["punycode"];
            }
        }
        return $cmd;
    }
}
