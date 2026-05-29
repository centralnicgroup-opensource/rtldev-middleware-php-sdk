<?php

declare(strict_types=1);

/**
 * CNIC\CNR
 * Copyright © CentralNic Group PLC
 */

namespace CNIC\CNR;

/**
 * CNR SocketConfig
 *
 * @package CNIC\CNR
 */
class SocketConfig
{
    /**
     * Parameter to trigger creation of a backend session
     */
    private bool $persistent = false;

    /**
     * account name
     */
    protected string $login = "";

    /**
     * account password
     */
    protected string $pw = "";

    /**
     * remote ip address (ip filter)
     */
    protected string $remoteaddr = "";

    /**
     * API session id
     */
    protected string $session = "";

    /**
     * subuser account name (subuser specific data view)
     */
    protected string $user = "";

    /**
     * list of http request parameters
     * @var array<string>
     */
    protected array $parameters;

    /**
     * Constructor
     * @param array<mixed> $parameters
     */
    public function __construct(array $parameters)
    {
        $this->parameters = $parameters;
    }

    /**
     * Get POST data container of connection data
     * @param array<mixed> $command API Command to request
     * @param bool $secured if password has to be returned "hidden"
     * @return array<string,string>
     */
    protected function getPOSTDataParams(array $command, bool $secured): array
    {
        $params = [];
        if (strlen($this->login) !== 0) {
            $params[$this->parameters["login"]] = $this->login;
        }
        if (strlen($this->pw) !== 0) {
            $params[$this->parameters["password"]] = $secured ? "***" : $this->pw;
        }
        if (strlen($this->remoteaddr) && isset($this->parameters["ipfilter"])) {
            $params[$this->parameters["ipfilter"]] = $this->remoteaddr;
        }
        if (strlen($this->session) !== 0) {
            $params[$this->parameters["session"]] = $this->session;
        }
        if (strlen($this->user) && isset($this->parameters["subuser"])) {
            $params[$this->parameters["subuser"]] = $this->user;
        }
        if ($command !== [] && isset($this->parameters["command"])) {
            $newcommand = "";
            foreach ($command as $key => $val) {
                if (is_null($val)) {
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
     * Create POST data string out of connection data
     *
     * @param array<int|string,mixed> $command API Command to request
     * @param bool $secured if password has to be returned "hidden"
     * @return string POST data string
     */
    public function getPOSTData(array $command = [], bool $secured = false): string
    {
        $params = $this->getPOSTDataParams($command, $secured);
        if ($this->getPersistent()) {
            $params["persistent"] = 1;
        }
        if (strlen($this->user) !== 0) {
            $params[$this->parameters["command"]] = (string)$params[$this->parameters["command"]] . "\nSUBUSER={$this->user}";
        }
        return http_build_query($params); // RFC1738 x-www-form-urlencoded as default
    }

    /**
     * Add persistent parameter to request (request API session)
     *
     * @return $this
     */
    public function setPersistent(bool $value = false)
    {
        $this->persistent = $value;
        return $this;
    }

    /**
     * Get persistent parameter returned
     *
     */
    public function getPersistent(): bool
    {
        return $this->persistent;
    }

    /**
     * Get API Session ID in use
     */
    public function getSession(): string
    {
        return $this->session;
    }

    /**
     * Set account name to use
     * @param string $value account name
     * @return $this
     */
    public function setLogin(string $value)
    {
        $this->session = "";
        $this->login = $value;
        return $this;
    }

    /**
     * Get current login (including role)
     */
    public function getLogin(): string
    {
        return $this->login;
    }

    /**
     * Set account password to use
     * @param string $value account password
     * @return $this
     */
    public function setPassword(string $value)
    {
        $this->session = "";
        $this->pw = $value;
        return $this;
    }

    /**
     * Set Remote IP Address to use
     * @param string $value remote ip address
     * @return $this
     */
    public function setRemoteAddress(string $value)
    {
        $this->remoteaddr = $value;
        return $this;
    }

    /**
     * Set API Session ID to use
     *
     * @param string $value API Session ID
     * @return $this
     */
    public function setSession(string $value)
    {
        $this->session = $value;
        // $this->login = "";
        $this->pw = "";
        return $this;
    }

    /**
     * Set subuser account name (for subuser data view)
     * @param string $value subuser account name
     * @return $this
     */
    public function setUser(string $value)
    {
        $this->user = $value;
        return $this;
    }
}
