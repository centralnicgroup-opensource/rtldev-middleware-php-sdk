<?php

#declare(strict_types=1);

/**
 * CNIC\CNR
 * Copyright © CentralNic Group PLC
 */

namespace CNIC\CNR;

use CNIC\IDNA\Factory\ConverterFactory;

/**
 * CNR API Client
 *
 * @package CNIC\CNR
 */

class SessionClient extends \CNIC\HEXONET\SessionClient
{
    public function __construct()
    {
        parent::__construct(implode(DIRECTORY_SEPARATOR, [__DIR__, "config.json"]));
    }
    /**
     * Perform API login to start session-based communication
     * @param string $otp optional one time password
     * @return \CNIC\HEXONET\Response Response
     */
    public function login($otp = "")
    {
        $this->setOTP($otp);
        $rr = $this->request([
            "COMMAND" => "StartSession",
            "persistent" => 1
        ]);
        if ($rr->isSuccess()) {
            $col = $rr->getColumn("SESSIONID");
            $this->setSession($col ? $col->getData()[0] : "");
        }
        return $rr;
    }

    /**
     * Perform API login to start session-based communication.
     * Use given specific command parameters.
     * @param array<string> $params given specific command parameters
     * @param string $otp optional one time password
     * @return \CNIC\HEXONET\Response Response
     */
    public function loginExtended($params, $otp = "")
    {
        // no further parameters supported, falling back to standard
        return $this->login($otp);
    }

    /**
     * Perform API logout to close API session in use
     * @return \CNIC\HEXONET\Response Response
     */
    public function logout()
    {
        $rr = $this->request(["COMMAND" => "StopSession"]);
        if ($rr->isSuccess()) {
            $this->setSession();
        }
        return $rr;
    }

    /**
     * Convert domain names to idn + punycode if necessary
     * @param array<string> $domains given specific command parameters
     * @return array<mixed>
     */
    public function IDNConvert($domains)
    {
        return ConverterFactory::convert($domains);
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
