<?php

declare(strict_types=1);

/**
 * CNIC\CNR
 * Copyright © Team Internet Group PLC
 */

namespace CNIC\CNR;

use CNIC\AbstractClient;
use CNIC\CNR\Logger as L;
use CNIC\CNR\Response;
use CNIC\CommandFormatter;
use CNIC\CommandRedactor;
use CNIC\Exception\PaginationException;
use CNIC\Exception\UnsupportedFeatureException;
use CNIC\LogSinkInterface;
use CNIC\RoleCredentialsInterface;

/**
 * CNR API Client
 *
 * Home of the two capabilities the CNR platform has and the flat IBS/Moniker
 * platform does not: **API sessions** — the accessors
 * {@see getSession()}/{@see setSession()} plus the lifecycle
 * {@see login()}/{@see logout()}/{@see saveSession()}/{@see reuseSession()} —
 * and **role credentials** ({@see \CNIC\RoleCredentialsInterface}). Both read
 * state that only {@see SocketConfig} carries, which is why they live here
 * rather than on {@see AbstractClient} — see the note there.
 *
 * The lifecycle methods were a `SessionCapable` trait used by a `SessionClient`
 * subclass until RSRMID-2969. The trait had one host, the subclass added nothing
 * else, and nothing in the SDK ever produced a session-less CNR client — so the
 * split bought a distinction no code made, at the price of three file opens to
 * find `login()`. It also carried a `@psalm-require-extends Client`, which is
 * the trait admitting it was only ever a part of this class. Do not reintroduce
 * either: if a genuinely session-less CNR client ever becomes a real use case,
 * that is a new type with a narrower contract, not a trait extracted back out.
 *
 * @psalm-api
 * @package CNIC\CNR
 */
class Client extends AbstractClient implements RoleCredentialsInterface
{
    /**
     * Narrowed from {@see AbstractClient::__construct()}'s `?AbstractSocketConfig`,
     * mirroring the covariant {@see newSocketConfig()} factory below.
     *
     * The narrowing is the point: `new Client(new \CNIC\IBS\SocketConfig())` has to
     * be an analysis error at the call site, not an
     * {@see UnsupportedFeatureException} thrown later from {@see getSocketConfig()}.
     * PHP exempts constructors from LSP under class inheritance, so a subclass may
     * narrow a parameter here where it could not on any other method; PHPStan and
     * Psalm both accept the declaration and enforce it against callers.
     *
     * @param SocketConfig|null $socketConfig CNR connection configuration to adopt;
     *        null builds the brand default
     */
    public function __construct(?SocketConfig $socketConfig = null)
    {
        parent::__construct($socketConfig);
        // Two steps because Psalm cannot infer a bare `new \WeakMap()`'s template
        // parameters from the assignment target — it widens to WeakMap<object, mixed>
        // and reports the narrowing as a PropertyTypeCoercion.
        /** @var \WeakMap<Response, array<string, string>> $sentCommands */
        $sentCommands = new \WeakMap();
        $this->sentCommands = $sentCommands;
    }

    /**
     * The exact, still-unmasked command handed to {@see newResponse()} for every
     * Response this client produced, so {@see requestNextResponsePage()} can
     * continue a paginated query with the parameters that were actually sent
     * rather than with the response's deliberately lossy copy (RSRMID-2975).
     *
     * Keyed weakly: an entry disappears together with the Response it describes,
     * so walking a long list does not accumulate commands for pages the caller
     * has already dropped.
     *
     * **It lives beside the Response, not on it, and that is the whole point.**
     * {@see \CNIC\AbstractResponse} masks the brand's sensitive command keys
     * *before* storing the command precisely so their values can never be read
     * back off a Response — which is what keeps `print_r($response)`,
     * `var_dump()`, `json_encode()` and a custom logger free of an EPP auth code.
     * An `$unmaskedCommand` property with a `getUnmaskedCommand()` accessor would
     * fix this bug just as well and undo that guarantee at the same time: it grants
     * no capability the caller lacks (it already holds the command it passed to
     * {@see request()}), only new accidental-leak surface, and `__debugInfo()`
     * would cover only `var_dump()` of the four. Do not move it onto the Response.
     *
     * @var \WeakMap<Response, array<string, string>>
     */
    private \WeakMap $sentCommands;

    /**
     * Instantiate CNR SocketConfig
     */
    #[\Override]
    protected function newSocketConfig(): SocketConfig
    {
        return new SocketConfig();
    }

    /**
     * The CNR SocketConfig, narrowed from the shared {@see AbstractSocketConfig}
     * type of {@see AbstractClient::$socketConfig}.
     *
     * The one narrowing point for CNR's platform-specific config state (session,
     * persistent, role separator). A typed property cannot be re-declared with a
     * narrower type in PHP, so the covariant {@see newSocketConfig()} factory
     * cannot inform the property's type — this accessor carries that knowledge
     * instead, in exactly one place, rather than each caller asserting.
     *
     * It is the covariant override of {@see AbstractClient::getSocketConfig()}, and
     * deliberately the only one: two methods narrowing the same property would be
     * two places to keep in step. Consumers holding `CNR\Client` therefore reach
     * `getSession()`/`setPersistent()` with no narrowing of their own.
     *
     * The guard is unreachable for correctly-typed callers. There are two writers
     * of the property — the covariant newSocketConfig() above and the constructor
     * parameter (RSRMID-2966) — and both are narrowed to `SocketConfig`, so neither
     * can seat a foreign config without an analysis error at the call site first. It
     * throws rather than `assert()`ing because `assert()` is compiled out when
     * `zend.assertions` is disabled, which would turn a subclass that returned the
     * wrong config into an undefined-method fatal instead of a named SDK exception.
     * @throws UnsupportedFeatureException if a subclass supplied a non-CNR config
     */
    #[\Override]
    public function getSocketConfig(): SocketConfig
    {
        if (!$this->socketConfig instanceof SocketConfig) {
            throw new UnsupportedFeatureException(
                "CNR session and role handling require a CNIC\\CNR\\SocketConfig, got "
                . $this->socketConfig::class . "."
            );
        }
        return $this->socketConfig;
    }

    /**
     * Get the API Session ID that is currently set, or null when there is none.
     *
     * CNR-only: IBS/Moniker have no session concept, so the method is absent there
     * rather than present and answering null.
     */
    public function getSession(): ?string
    {
        $sessid = $this->getSocketConfig()->getSession();
        return $sessid === "" ? null : $sessid;
    }

    /**
     * Set an API session id to be used for API communication.
     *
     * Setting a session clears the stored password: the two are alternative
     * credentials on the wire, and CNR's SocketConfig treats the newer one as
     * authoritative. That holds on the reset path too — `setSession("")` leaves
     * neither, so re-set the credentials to go back to password authentication
     * (see {@see SocketConfig::setSession()}).
     * @param string $session empty string resets it
     */
    public function setSession(string $session = ""): static
    {
        $this->getSocketConfig()->setSession($session);
        return $this;
    }

    /**
     * Perform API login to start session-based communication.
     *
     * The connection is made persistent for the duration of the login request
     * only, then put back: the session id, not the socket, is what the following
     * requests reuse.
     */
    public function login(): Response
    {
        $this->getSocketConfig()->setPersistent(true);
        $rr = $this->request();
        if ($rr->isSuccess()) {
            $this->setSession($rr->getColumn("SESSIONID")?->getStringByIndex(0) ?? "");
        }
        $this->getSocketConfig()->setPersistent(false);
        return $rr;
    }

    /**
     * Perform API logout to close the API session in use.
     *
     * The transport is closed whether or not the command succeeded — a failed
     * StopSession still leaves a connection this client will not reuse.
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

    /**
     * Instantiate the CNR logger writing to the given sink
     */
    #[\Override]
    protected function newLogger(LogSinkInterface $sink): L
    {
        return new L($sink);
    }

    /**
     * Perform API request using the given command
     * @param array<string, scalar|scalar[]|null> $cmd API command to request (optional for session login)
     * @param string $path endpoint path appended to the base URL (defaults to the CNR script path)
     */
    #[\Override]
    public function request(array $cmd = [], string $path = "api/call.cgi"): Response
    {
        $r = $this->performRequest($cmd, $path);
        assert($r instanceof Response);
        return $r;
    }

    /**
     * Flatten the given command into wire form (CNR uppercase key/value pairs)
     * and convert its IDN parameters to punycode.
     *
     * The IDN rewrite is CNR's alone — IBS/Moniker convert server-side — so it runs
     * here, in the brand hook, and not on {@see AbstractClient} behind a flag; see
     * {@see IDNCommandRewriter}. It must run *after* the flattening: the rules match
     * wire keys (`NAMESERVER0`, `OBJECTID`), not the caller's nested,
     * arbitrarily-cased input.
     * @param array<string, scalar|scalar[]|null> $cmd API command
     * @return array<string, string>
     */
    #[\Override]
    protected function buildCommand(array $cmd): array
    {
        return IDNCommandRewriter::rewrite(CommandFormatter::flattenCommand($cmd));
    }

    /**
     * Instantiate a CNR Response for the given raw payload.
     * @param array<string, string> $cmd flattened command that produced the response
     * @param array{CONNECTION_URL: string} $cfg connection config used for the request
     * @param string|null $error transport error, if any; non-null means $raw is unusable
     */
    #[\Override]
    protected function newResponse(string $raw, array $cmd, array $cfg, ?string $error = null): Response
    {
        $response = new Response($raw, $cmd, $cfg, $this->context, error: $error);
        $this->sentCommands[$response] = $cmd;
        return $response;
    }

    /**
     * Set Role Credentials to be used for API communication.
     *
     * CNR-only capability (see {@see \CNIC\RoleCredentialsInterface}): a role
     * login is the account id, the `":"` role separator and the role user id,
     * authenticated with that role user's own password.
     *
     * @param string $accountId empty string resets it
     * @param string $roleId empty string logs in as the account itself, without a role
     * @param string $password the role user's own password; empty string resets it
     */
    #[\Override]
    public function setRoleCredentials(string $accountId = "", string $roleId = "", string $password = ""): static
    {
        $login = $accountId;
        if ($roleId !== '') {
            $login .= $this->getSocketConfig()->getRoleSeparator() . $roleId;
        }
        return $this->setCredentials($login, $password);
    }

    /**
     * The command to continue $currentPage's query with.
     *
     * Command data comes from the command, pagination state from the response —
     * this method is the first half of that split. Reading the command off the
     * response instead was RSRMID-2975: `getCommand()` answers the *masked* copy,
     * so a list command carrying `AUTH` or `PASSWORD` (an EPP transfer auth code,
     * an account password field) had the literal mask re-sent as that parameter's
     * value on page 2 onward. Not a display artifact — it reached the wire. Note
     * what it is *not*: the client's own credentials travel as `s_login`/`s_pw`
     * off the {@see SocketConfig} and were never in the command, so every page
     * authenticated correctly and the damage was a corrupted *parameter* — which
     * the API may well accept, answering page 2 from a different result set than
     * page 1 rather than failing outright.
     *
     * A Response this client did not itself produce — constructed directly, or
     * returned by a different client instance — is not in the map. Falling back to
     * its masked command is safe exactly when nothing in it was masked, which is
     * the overwhelmingly common case and continues to work untouched; when
     * something *was* masked there is no unmasked copy anywhere to recover, so
     * this throws rather than putting the mask on the wire. Detection is by value
     * rather than by key, so it holds for a subclass that widened
     * `$sensitiveFields` beyond {@see SensitiveFields::KEYS}; the only false
     * positive is a caller legitimately sending the literal `"***"`.
     *
     * @return array<string, string>
     * @throws PaginationException if $currentPage came from elsewhere and its command is masked
     */
    private function continuationCommand(Response $currentPage): array
    {
        if (isset($this->sentCommands[$currentPage])) {
            /** @var array<string, string> $sent Psalm's WeakMap stub types offsetGet as TValue|null */
            $sent = $this->sentCommands[$currentPage];
            return $sent;
        }
        $cmd = $currentPage->getCommand();
        if (in_array(CommandRedactor::MASK, $cmd, true)) {
            throw new PaginationException(
                "Cannot continue pagination from a Response this client did not produce: its command still "
                . "carries the redaction mask (\"" . CommandRedactor::MASK . "\") in place of a sensitive "
                . "parameter, and re-sending it would put the mask on the wire. Pass a Response returned by "
                . "this client's own request()."
            );
        }
        return $cmd;
    }

    /**
     * Request the next page of list entries for the current list query
     *
     * The continuation is assembled from two sources, deliberately: the command
     * that produced $currentPage ({@see continuationCommand()}) and $currentPage's
     * own pagination state. Response data — `LIMIT`, `LAST` — is not masked and is
     * read straight off the response; command parameters are not.
     *
     * @throws PaginationException in case Command Parameter LAST is in use while using this method,
     *         or if $currentPage was produced elsewhere and its command is masked
     */
    public function requestNextResponsePage(Response $currentPage): ?Response
    {
        $mycmd = $this->continuationCommand($currentPage);
        if (array_key_exists("LAST", $mycmd)) {
            throw new PaginationException("Parameter LAST in use. Please remove it to avoid issues in requestNextPage.");
        }
        // Delegate the termination decision to the paginator so "is there a next
        // page?" lives in one place (Paginator::hasNextPage()) rather than being
        // re-derived from total/limit arithmetic here. This also subsumes the
        // former LIMIT<=0 guard: a non-positive page size makes hasNextPage()
        // return false, so requestAllResponsePages() terminates instead of
        // re-requesting the same page forever (see
        // testRequestNextResponsePageZeroLimit).
        //
        // The advance itself is the response's own next offset — LAST + 1 —
        // rather than command FIRST + LIMIT: identical to the old arithmetic for
        // an aligned page, but correct for an unaligned one, and it no longer
        // depends on the caller having sent FIRST at all.
        if (!$currentPage->getPagination()->hasNextPage()) {
            return null;
        }
        $limit = $currentPage->getRecordsLimitation();
        $last = $currentPage->getLastRecordIndex();
        if ($limit === null || $limit <= 0 || $last === null) {
            return null;
        }
        $mycmd["FIRST"] = $last + 1;
        $mycmd["LIMIT"] = $limit;
        return $this->request($mycmd);
    }

    /**
     * Request all pages/entries for the given query command
     * @param array<string, scalar|scalar[]|null> $cmd API list command to use
     * @return Response[]
     */
    public function requestAllResponsePages(array $cmd): array
    {
        $responses = [];
        $rr = $this->request(array_merge($cmd, ["FIRST" => 0]));
        $tmp = $rr;
        $idx = 0;
        do {
            $responses[$idx++] = $tmp;
            $tmp = $this->requestNextResponsePage($tmp);
        } while ($tmp instanceof Response);
        return $responses;
    }
}
