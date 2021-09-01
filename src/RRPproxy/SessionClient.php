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
     * @return Response Response
     */
    public function login($otp = "")
    {
        $this->setOTP($otp);
        $rr = $this->request([
            "COMMAND" => "StartSession",
            "PERSISTENT" => 1
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
     * @return Response Response
     */
    public function loginExtended($params, $otp = "")
    {
        // no further parameters supported, falling back to standard
        return $this->login($otp);
    }

    /**
     * Perform API logout to close API session in use
     * @return Response Response
     */
    public function logout()
    {
        $rr = $this->request(["COMMAND" => "StopSession"]);
        if ($rr->isSuccess()) {
            $this->setSession("");
        }
        return $rr;
    }
}
