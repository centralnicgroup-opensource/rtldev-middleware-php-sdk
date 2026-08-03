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
use CNIC\Exception\PaginationException;
use CNIC\Exception\UnsupportedFeatureException;
use CNIC\LogSinkInterface;
use CNIC\RoleCredentialsInterface;

/**
 * CNR API Client
 *
 * Home of the two capabilities the CNR platform has and the flat IBS/Moniker
 * platform does not: **API sessions** (`getSession()`/`setSession()`, plus
 * `login()`/`logout()` via the {@see SessionCapable} trait on
 * {@see SessionClient}) and **role credentials**
 * ({@see \CNIC\RoleCredentialsInterface}). Both read state that only
 * {@see SocketConfig} carries, which is why they live here rather than on
 * {@see AbstractClient} — see the note there.
 *
 * @psalm-api
 * @package CNIC\CNR
 */
class Client extends AbstractClient implements RoleCredentialsInterface
{
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
     * The guard is unreachable in practice, since newSocketConfig() above is the
     * only writer and it is covariant. It throws rather than `assert()`ing
     * because `assert()` is compiled out when `zend.assertions` is disabled,
     * which would turn a subclass that returned the wrong config into an
     * undefined-method fatal instead of a named SDK exception.
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
     */
    #[\Override]
    protected function newResponse(string $raw, array $cmd, array $cfg): Response
    {
        return new Response($raw, $cmd, $cfg, $this->context);
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
     * Request the next page of list entries for the current list query
     * @throws PaginationException in case Command Parameter LAST is in use while using this method
     */
    public function requestNextResponsePage(Response $currentPage): ?Response
    {
        $mycmd = $currentPage->getCommand();
        if (array_key_exists("LAST", $mycmd)) {
            throw new PaginationException("Parameter LAST in use. Please remove it to avoid issues in requestNextPage.");
        }
        // Delegate the termination decision to the Response pagination helper so
        // "is there a next page?" lives in one place (Response::hasNextPage())
        // rather than being re-derived from total/limit arithmetic here. This
        // also subsumes the former LIMIT<=0 guard: a non-positive page size makes
        // getCurrentPageNumber() null, so hasNextPage() returns false and
        // requestAllResponsePages() terminates instead of re-requesting the same
        // page forever (see testRequestNextResponsePageZeroLimit).
        if (!$currentPage->hasNextPage()) {
            return null;
        }
        $first = 0;
        if (array_key_exists("FIRST", $mycmd)) {
            $first = (int) $mycmd["FIRST"];
        }
        $limit = $currentPage->getRecordsLimitation();
        $mycmd["FIRST"] = $first + $limit;
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
