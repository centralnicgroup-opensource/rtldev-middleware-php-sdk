<?php

#declare(strict_types=1);

/**
 * CNIC\HEXONET
 * Copyright © CentralNic Group PLC
 */

namespace CNIC\HEXONET;

/**
 * HEXONET Session API Client
 *
 * @package CNIC\HEXONET
 */

class SessionClient extends Client
{
    public function __construct(string $cfgfile = "")
    {
        if (empty($cfgfile)) {
            parent::__construct(implode(DIRECTORY_SEPARATOR, [__DIR__, "config.json"]));
        } else {
            parent::__construct($cfgfile);
        }
    }

    /**
     * Perform API login to start session-based communication
     * @param string $otp optional one time password
     * @return Response Response
     */
    public function login($otp = "")
    {
        $this->setOTP($otp);
        $rr = $this->request(["COMMAND" => "StartSession"]);
        if ($rr->isSuccess()) {
            $col = $rr->getColumn("SESSION");
            $this->setSession($col ? $col->getData()[0] : "");
        }
        return $rr;
    }

    /**
     * Perform API login to start session-based communication.
     * Use given specific command parameters.
     * @param array<string> $params given specific command parameters
     * @param string $otp optional one time password
     * @return Response Response
     */
    public function loginExtended($params, $otp = "")
    {
        $this->setOTP($otp);
        $rr = $this->request(array_merge(
            ["COMMAND" => "StartSession"],
            $params
        ));
        if ($rr->isSuccess()) {
            $col = $rr->getColumn("SESSION");
            $this->setSession($col ? $col->getData()[0] : "");
        }
        return $rr;
    }

    /**
     * Perform API logout to close API session in use
     * @return Response Response
     */
    public function logout()
    {
        $rr = $this->request(["COMMAND" => "EndSession"]);
        if ($rr->isSuccess()) {
            $this->setSession("");
        }
        return $rr;
    }
}
