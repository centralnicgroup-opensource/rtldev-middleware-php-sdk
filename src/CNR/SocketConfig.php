<?php

declare(strict_types=1);

/**
 * CNIC\CNR
 * Copyright © Team Internet Group PLC
 */

namespace CNIC\CNR;

use CNIC\AbstractSocketConfig;

/**
 * CNR SocketConfig
 *
 * @package CNIC\CNR
 */
final class SocketConfig extends AbstractSocketConfig
{
    protected string $oteUrl = "https://api-ote.rrpproxy.net/api/call.cgi";
    protected string $liveUrl = "https://api.rrpproxy.net/api/call.cgi";
    protected int $socketTimeout = 300;
    protected bool $needsIDNConvert = true;
    protected string $roleSeparator = ":";

    /**
     * Parameter to trigger creation of a backend session
     */
    private bool $persistent = false;

    /**
     * API session id
     */
    private string $session = "";

    /**
     * list of http request parameters
     * @var array{login: string, password: string, command: string, session: string}
     */
    private array $parameters = [
        "login"    => "s_login",
        "password" => "s_pw",
        "command"  => "s_command",
        "session"  => "s_sessionid",
    ];

    /**
     * Get POST data container of connection data
     * @param array<string, string|null> $command API Command to request
     * @param bool $secured if password has to be returned "hidden"
     * @return array<string, string|null>
     */
    #[\Override]
    protected function getPOSTDataParams(array $command, bool $secured): array
    {
        $params = [];
        if (strlen($this->login) !== 0) {
            $params[$this->parameters["login"]] = $this->login;
        }
        if (strlen($this->pw) !== 0) {
            $params[$this->parameters["password"]] = $secured ? "***" : $this->pw;
        }
        if (strlen($this->session) !== 0) {
            $params[$this->parameters["session"]] = $this->session;
        }
        if ($command !== []) {
            $newcommand = "";
            foreach ($command as $key => $val) {
                if ($val === null) {
                    continue;
                }
                if ($secured && preg_match("/^PASSWORD$/i", $key)) {
                    $val = "***";
                }
                $newcommand .= "{$key}={$val}\n";
            }
            $params[$this->parameters["command"]] = substr($newcommand, 0, -1);
        }
        return $params;
    }

    /**
     * Add persistent parameter to request (request API session)
     */
    #[\Override]
    public function setPersistent(bool $value = false): static
    {
        $this->persistent = $value;
        return $this;
    }

    /**
     * Get persistent parameter returned
     */
    #[\Override]
    public function getPersistent(): bool
    {
        return $this->persistent;
    }

    /**
     * Get API Session ID in use
     */
    #[\Override]
    public function getSession(): string
    {
        return $this->session;
    }

    /**
     * Set account name to use
     * @param string $value account name
     */
    #[\Override]
    public function setLogin(string $value): static
    {
        $this->session = "";
        $this->login = $value;
        return $this;
    }

    /**
     * Set account password to use
     * @param string $value account password
     */
    #[\Override]
    public function setPassword(string $value): static
    {
        $this->session = "";
        $this->pw = $value;
        return $this;
    }

    /**
     * Set API Session ID to use
     * @param string $value API Session ID
     */
    #[\Override]
    public function setSession(string $value = ""): static
    {
        $this->session = $value;
        $this->pw = "";
        return $this;
    }
}
