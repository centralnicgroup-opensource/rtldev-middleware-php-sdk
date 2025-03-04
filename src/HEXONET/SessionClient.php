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
    /**
     * Constructor
     * @throws \Exception
     */
    public function __construct()
    {
        $reflection = new \ReflectionClass(get_called_class());
        $fname = $reflection->getFileName();
        if ($fname === false) {
            throw new \Exception("Reflection failed");
        }
        $cfgpath = implode(DIRECTORY_SEPARATOR, [dirname($fname), "config.json"]);
        parent::__construct($cfgpath);
    }

    /**
     * Perform API login to start session-based communication
     * @param string $otp optional one time password
     * @return Response
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
     * @param array<string,mixed> $params given specific command parameters
     * @param string $otp optional one time password
     * @return Response
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
     * @return Response
     */
    public function logout()
    {
        $rr = $this->request(["COMMAND" => "EndSession"]);
        if ($rr->isSuccess()) {
            $this->setSession("");
        }
        $this->close();
        return $rr;
    }

    /**
     * Apply session data (session id and system entity) to given php session object
     * @param array<string,mixed> $session php session instance ($_SESSION)
     * @return $this
     */
    public function saveSession(&$session)
    {
        $session["socketcfg"] = [
            "entity" => $this->socketConfig->getSystemEntity(),
            "session" => $this->socketConfig->getSession()
        ];
        return $this;
    }

    /**
     * Use existing configuration out of php session object
     * to rebuild and reuse connection settings
     * @param array<string,mixed> $session php session object ($_SESSION)
     * @return $this
     */
    public function reuseSession(&$session)
    {
        $this->socketConfig->setSystemEntity($session["socketcfg"]["entity"]);
        $this->setSession($session["socketcfg"]["session"]);
        return $this;
    }
}
