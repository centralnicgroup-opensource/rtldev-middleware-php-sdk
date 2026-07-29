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
 * Owns the three settings that are CNR platform concepts rather than shared
 * transport configuration, and that RSRMID-2920 moved down here off
 * {@see AbstractSocketConfig}:
 *
 * - the **API session id** and the **`persistent` flag** that requests one.
 *   These used to exist twice — as null-object stubs on the shared base and as
 *   real state here — so `setSession()` on an IBS/Moniker client looked accepted
 *   (the setter is fluent) and was discarded. They now exist only here, which
 *   makes the mismatch a call-site type error instead of a silent no-op.
 * - the **role separator**, whose single consumer is
 *   {@see \CNIC\CNR\Client::setRoleCredentials()} — itself already CNR-only via
 *   {@see \CNIC\RoleCredentialsInterface}. Keeping the separator on the shared
 *   base left half of that split behind.
 *
 * @package CNIC\CNR
 */
final class SocketConfig extends AbstractSocketConfig
{
    protected string $oteUrl = "https://api-ote.rrpproxy.net/";
    protected string $liveUrl = "https://api.rrpproxy.net/";
    protected int $socketTimeout = 300;

    /**
     * Separator between the account id and the role user id in a role login
     */
    private string $roleSeparator = ":";

    /**
     * CNR carries sensitive data under upper-case command keys. Mirrors
     * {@see \CNIC\CNR\Response::$sensitiveFields}.
     * @var string[]
     */
    protected array $sensitiveFields = ["PASSWORD", "AUTH"];

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
            if ($secured) {
                $command = $this->maskSensitiveCommand($command);
            }
            $newcommand = "";
            foreach ($command as $key => $val) {
                if ($val === null) {
                    continue;
                }
                $newcommand .= "{$key}={$val}\n";
            }
            $params[$this->parameters["command"]] = substr($newcommand, 0, -1);
        }
        // Appended last so the encoded body is byte-identical to what the shared
        // getPOSTData() used to produce when it owned this branch (RSRMID-2920).
        if ($this->getPersistent()) {
            $params["persistent"] = "1";
        }
        return $params;
    }

    /**
     * Add persistent parameter to request (request API session)
     */
    public function setPersistent(bool $value = false): static
    {
        $this->persistent = $value;
        return $this;
    }

    /**
     * Get persistent parameter returned
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
     * Get the separator between account id and role user id in a role login
     */
    public function getRoleSeparator(): string
    {
        return $this->roleSeparator;
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
    public function setSession(string $value = ""): static
    {
        $this->session = $value;
        $this->pw = "";
        return $this;
    }
}
