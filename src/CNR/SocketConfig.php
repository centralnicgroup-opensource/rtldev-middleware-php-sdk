<?php

declare(strict_types=1);

/**
 * CNIC\CNR
 * Copyright © Team Internet Group PLC
 */

namespace CNIC\CNR;

use CNIC\AbstractSocketConfig;
use CNIC\CommandRedactor;

/**
 * CNR SocketConfig
 *
 * Owns the three settings that are CNR platform concepts rather than shared
 * transport configuration, and must not be hoisted onto
 * {@see AbstractSocketConfig}:
 *
 * - the **API session id** and the **`persistent` flag** that requests one. As
 *   stubs on the shared base they made `setSession()` on an IBS/Moniker client look
 *   accepted (the setter is fluent) while the value was discarded. Living only here
 *   makes that mismatch a call-site type error instead of a silent no-op.
 * - the **role separator**, whose single consumer is
 *   {@see \CNIC\CNR\Client::setRoleCredentials()} — itself already CNR-only via
 *   {@see \CNIC\RoleCredentialsInterface}.
 *
 * Guarded by tests/ClientSessionSeamTest.php.
 *
 * @package CNIC\CNR
 */
final class SocketConfig extends AbstractSocketConfig
{
    protected string $oteUrl = "https://api-ote.rrpproxy.net/";
    protected string $liveUrl = "https://api.rrpproxy.net/";

    /**
     * Separator between the account id and the role user id in a role login
     */
    private string $roleSeparator = ":";

    /**
     * CNR carries sensitive data under upper-case command keys. Declared once
     * in {@see SensitiveFields::KEYS}, shared with {@see \CNIC\CNR\Response}.
     * @var string[]
     */
    protected array $sensitiveFields = SensitiveFields::KEYS;

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
     * @return array<string, string|null>
     */
    #[\Override]
    protected function getPOSTDataParams(array $command, bool $maskSecrets): array
    {
        $params = [];
        if (strlen($this->login) !== 0) {
            $params[$this->parameters["login"]] = $this->login;
        }
        if (strlen($this->password) !== 0) {
            $params[$this->parameters["password"]] = $maskSecrets ? CommandRedactor::MASK : $this->password;
        }
        // Masked for the same reason s_pw is: a session id is not a lesser credential
        // than the password but an alternative to it — see setSession(), which clears
        // the password because the newer of the two is authoritative on the wire.
        // Masking one and logging the other left the debug body carrying a working
        // credential on exactly the persistent-session path, where there is no
        // password left to mask.
        if (strlen($this->session) !== 0) {
            $params[$this->parameters["session"]] = $maskSecrets ? CommandRedactor::MASK : $this->session;
        }
        if ($command !== []) {
            if ($maskSecrets) {
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
        // Appended last, which is what keeps the encoded body byte-identical to the
        // CNR wire format; a test asserts the exact bytes.
        if ($this->getPersistent()) {
            $params["persistent"] = "1";
        }
        return $params;
    }

    /**
     * Add persistent parameter to request (request API session)
     */
    public function setPersistent(bool $persistent = false): static
    {
        $this->persistent = $persistent;
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
     */
    #[\Override]
    public function setLogin(string $login): static
    {
        $this->session = "";
        $this->login = $login;
        return $this;
    }

    /**
     * Set account password to use
     */
    #[\Override]
    public function setPassword(string $password): static
    {
        $this->session = "";
        $this->password = $password;
        return $this;
    }

    /**
     * Set API Session ID to use.
     *
     * Always clears the stored password — a session and a password are alternative
     * credentials on the wire and the newer one is authoritative. Note this holds
     * on the **reset** path too: `setSession("")` drops the session *and* leaves no
     * password behind it, so the next request carries only the login. Call
     * `setLogin()`/`setPassword()` again to get back to password authentication.
     * @param string $session empty string resets it
     */
    public function setSession(string $session = ""): static
    {
        $this->session = $session;
        $this->password = "";
        return $this;
    }
}
