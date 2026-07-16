<?php

declare(strict_types=1);

/**
 * CNIC\CNR
 * Copyright © Team Internet Group PLC
 */

namespace CNIC\CNR;

/**
 * Provides session-based API communication methods.
 * Use this trait in SessionClient classes whose underlying API supports
 * persistent sessions (login/logout lifecycle).
 *
 * @package CNIC\CNR
 */
trait SessionCapable
{
    /**
     * Perform API login to start session-based communication
     */
    public function login(): Response
    {
        $this->socketConfig->setPersistent(true);
        $rr = $this->request();
        if ($rr->isSuccess()) {
            $col = $rr->getColumn("SESSIONID");
            $this->setSession($col instanceof Column ? $col->getData()[0] : "");
        }
        $this->socketConfig->setPersistent(false);
        return $rr;
    }

    /**
     * Perform API logout to close API session in use
     */
    public function logout(): Response
    {
        $rr = $this->request(["COMMAND" => "StopSession"]);
        if ($rr->isSuccess()) {
            $this->setSession();
        }
        $this->close();
        return $rr;
    }

    /**
     * Apply session data to a PHP session object
     *
     * @param array<string,mixed> $session php session instance ($_SESSION)
     * @return $this
     */
    public function saveSession(array &$session): static
    {
        $session["socketcfg"] = [
            "login"   => $this->socketConfig->getLogin(),
            "session" => $this->socketConfig->getSession()
        ];
        return $this;
    }

    /**
     * Rebuild connection settings from a PHP session object
     *
     * @param array<string,mixed> $session php session object ($_SESSION)
     * @return $this
     */
    public function reuseSession(array $session): static
    {
        if (
            isset($session["socketcfg"]) &&
            is_array($session["socketcfg"]) &&
            isset($session["socketcfg"]["login"]) &&
            is_string($session["socketcfg"]["login"]) &&
            isset($session["socketcfg"]["session"]) &&
            is_string($session["socketcfg"]["session"])
        ) {
            $this->setCredentials($session["socketcfg"]["login"]);
            $this->setSession($session["socketcfg"]["session"]);
        }
        return $this;
    }
}
