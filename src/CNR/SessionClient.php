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
class SessionClient extends \CNIC\CNR\Client
{
    /**
     * Constructor
     *
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
     *
     * @return \CNIC\CNR\Response
     */
    public function login()
    {
        $this->socketConfig->setPersistent(true);
        $rr = $this->request();
        if ($rr->isSuccess()) {
            $col = $rr->getColumn("SESSIONID");
            $this->setSession($col ? $col->getData()[0] : "");
        }
        $this->socketConfig->setPersistent(false);
        return $rr;
    }

    /**
     * Perform API logout to close API session in use
     *
     * @return \CNIC\CNR\Response
     */
    public function logout()
    {
        $rr = $this->request(["COMMAND" => "StopSession"]);
        if ($rr->isSuccess()) {
            $this->setSession();
        }
        $this->close();
        return $rr;
    }

    /**
     * Apply session data (session id and system entity) to given php session object
     *
     * @param array<string,mixed> $session php session instance ($_SESSION)
     * @return $this
     */
    public function saveSession(&$session)
    {
        $session["socketcfg"] = [
            "login" => $this->socketConfig->getLogin(),
            "session" => $this->socketConfig->getSession()
        ];
        return $this;
    }

    /**
     * Use existing configuration out of php session object
     * to rebuild and reuse connection settings
     *
     * @param array<string,mixed> $session php session object ($_SESSION)
     * @return $this
     */
    public function reuseSession(&$session)
    {
        if (isset($session["socketcfg"]["login"], $session["socketcfg"]["session"])) {
            $this->setCredentials($session["socketcfg"]["login"]);
            $this->setSession($session["socketcfg"]["session"]);
        }
        return $this;
    }
}
