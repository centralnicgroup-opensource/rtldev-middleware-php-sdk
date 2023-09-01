<?php

#declare(strict_types=1);

/**
 * CNIC\CNR
 * Copyright © CentralNic Group PLC
 */

namespace CNIC\CNR;

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
     * Convert domain names to idn + punycode if necessary
     * @param array $domains list of domain names (or tlds)
     * @return array
     */
    public function IDNConvert($domains)
    {
        $results = [];
        foreach ($domains as $idx => $d) {
            $results[$idx] = [
                "PUNYCODE" => $d,
                "IDN" => $d
            ];
        }
        if ($this->settings["needsIDNConvert"]) {
            foreach ($domains as $idx => $domain) {
                $nontransitional = (bool)preg_match("/\.(be|ca|de|fr|pm|re|swiss|tf|wf|yt)\.?$/i", $domain);
                $tmp = idn_to_ascii(
                    $domain,
                    ($nontransitional) ?
                        IDNA_NONTRANSITIONAL_TO_ASCII :
                        IDNA_DEFAULT,
                    INTL_IDNA_VARIANT_UTS46
                );
                if ($tmp === false) {
                    continue;
                }
                if (preg_match("/xn--/", $tmp)) {
                    $results[$idx]["PUNYCODE"] = $tmp;
                }
                $tmp = idn_to_utf8(
                    $results[$idx]["PUNYCODE"],
                    ($nontransitional) ?
                        IDNA_NONTRANSITIONAL_TO_ASCII :
                        IDNA_DEFAULT,
                    INTL_IDNA_VARIANT_UTS46
                );
                if (!empty($tmp)) {
                    $results[$idx]["IDN"] = $tmp;
                }
            }
        }
        return $results;
    }

    /**
     * Auto convert API command parameters to punycode, if necessary.
     * @param array $cmd API command
     * @return array
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
                $cmd[$idxs[$idx]] = $row["PUNYCODE"];
            }
        }
        return $cmd;
    }
}
