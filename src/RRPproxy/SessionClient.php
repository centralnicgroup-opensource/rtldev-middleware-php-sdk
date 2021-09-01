<?php

#declare(strict_types=1);

/**
 * CNIC\RRPproxy
 * Copyright © CentralNic Group PLC
 */

namespace CNIC\RRPproxy;

/**
 * RRPproxy API Client
 *
 * @package CNIC\RRPproxy
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
     * @param array $params given specific command parameters
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
     * Auto convert API command parameters to punycode, if necessary.
     * @param array|string $cmd API command
     * @return array
     */
    protected function autoIDNConvert($cmd)
    {
        // only convert if configured for the registrar
        // and ignore commands in string format (even deprecated)
        if (
            !$this->settings["needsIDNConvert"]
            || !function_exists("idn_to_ascii")
            || is_string($cmd)
        ) {
            return $cmd;
        }

        $asciipattern = "/^[a-zA-Z0-9\.-]+$/";
        $keypattern = "/^(NAMESERVER|NS|DNSZONE)([0-9]*)$/i";// DOMAIN params get auto-converted by API, RSRBE-7149 for NS coverage
        $objclasspattern = "/^(DOMAIN(APPLICATION|BLOCKING)?|NAMESERVER|NS)$/i";
        foreach ($cmd as $key => $val) {
            if (
                (
                    (bool)preg_match($keypattern, $key)
                    || (
                        $key === "OBJECTID"
                        && isset($cmd["OBJECTCLASS"])
                        && (bool)preg_match($objclasspattern, $cmd["OBJECTCLASS"])
                    )
                )
                && !(bool)preg_match($asciipattern, $val)
            ) {
                $tmp = idn_to_ascii(
                    $val,
                    ((bool)preg_match("/\.(be|ca|de|fr|pm|re|swiss|tf|wf|yt)\.?$/i", $val)) ?
                        IDNA_NONTRANSITIONAL_TO_ASCII :
                        IDNA_DEFAULT,
                    INTL_IDNA_VARIANT_UTS46
                );
                if (preg_match("/xn--/", $tmp)) {
                    $cmd[$key] = $tmp;
                }
            }
        }
        return $cmd;
    }
}
