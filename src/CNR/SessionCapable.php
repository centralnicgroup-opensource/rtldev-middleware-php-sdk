<?php

declare(strict_types=1);

/**
 * CNIC\CNR
 * Copyright © Team Internet Group PLC
 */

namespace CNIC\CNR;

/**
 * Provides session-based API communication methods.
 *
 * CNR-only, and only usable on a {@see Client} — besides the shared client
 * members (`request()`, `close()`, `setCredentials()`) the trait reads CNR's
 * session state through {@see Client::getSocketConfig()} (covariantly narrowed to
 * {@see SocketConfig} there) and {@see Client::setSession()}. The
 * `@psalm-require-extends` tag below makes that
 * host requirement enforceable instead of a comment (PHPStan honours the same
 * tag, and rejects a second phpstan-prefixed copy of it): composing this trait into
 * anything but a `CNR\Client` is a static analysis error rather than a fatal on
 * first call. It is scoped to this namespace on purpose: the IBS/Moniker platform
 * has no login/logout lifecycle, and the empty `SessionClient` subclasses that
 * used to advertise one were removed in RSRMID-2920.
 *
 * @psalm-require-extends Client
 * @package CNIC\CNR
 */
trait SessionCapable
{
    /**
     * Perform API login to start session-based communication
     */
    public function login(): Response
    {
        $this->getSocketConfig()->setPersistent(true);
        $rr = $this->request();
        if ($rr->isSuccess()) {
            $col = $rr->getColumn("SESSIONID");
            $this->setSession($col instanceof Column ? $col->getData()[0] : "");
        }
        $this->getSocketConfig()->setPersistent(false);
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
     */
    public function saveSession(array &$session): static
    {
        $session["socketcfg"] = [
            "login"   => $this->getSocketConfig()->getLogin(),
            "session" => $this->getSocketConfig()->getSession()
        ];
        return $this;
    }

    /**
     * Rebuild connection settings from a PHP session object.
     *
     * The two calls are ordered, not interchangeable: `setCredentials()` clears
     * the session id (a session and a password are alternative credentials, and
     * CNR's SocketConfig treats the newer one as authoritative), so restoring the
     * session second is what makes this work.
     *
     * @param array<string,mixed> $session php session object ($_SESSION)
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
