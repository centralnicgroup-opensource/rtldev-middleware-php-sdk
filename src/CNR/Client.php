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
     * Perform API request using the given command
     *
     * @param array<mixed> $cmd API command to request
     * @return Response
     */
    public function request($cmd = [])
    {
        $r = parent::request($cmd);
        return new Response($r->getPlain(), $r->getCommand());
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

    /**
     * Auto convert API command parameters to punycode, if necessary.
     *
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
