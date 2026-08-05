<?php

declare(strict_types=1);

/**
 * CNIC
 * Copyright © Team Internet Group PLC
 */

namespace CNIC;

use CNIC\AbstractSocketConfig;
use CNIC\Exception\InvalidConfigurationException;
use CNIC\Exception\UnsupportedFeatureException;
use CNIC\IDNA\Factory\ConverterFactory;
use CNIC\LoggerInterface;
use CNIC\LogSinkInterface;
use CNIC\ResponseInterface;
use CNIC\System;

/**
 * Shared foundation for all registrar API clients.
 * Concrete subclasses provide the request() implementation, the default
 * logger, and the appropriate SocketConfig subtype.
 *
 * ## Where configuration lives
 *
 * Not here. Connection configuration has one home — {@see AbstractSocketConfig} —
 * reachable through {@see getSocketConfig()}. What lives here is client
 * *behaviour*: the logger and debug flag, the response context, the transport
 * instance, and the SDK's own identity (`VERSION`/`$userAgent`, versioned with this class
 * and released from it). Do not add a copy of a config-owned value; guarded by
 * tests/ClientConfigSeamTest.php.
 *
 * The configuration methods below are forwarders, and deliberately kept: they are
 * the documented ergonomic surface (`$cl->useOTESystem()->setCredentials(...)`)
 * and they read and write the config's state rather than a copy of it, so a
 * forwarder cannot disagree with the config. A *new* setting needs no forwarder —
 * `getSocketConfig()` is the accessor whose absence let ~26 of these accumulate.
 *
 * Only capabilities every brand can actually honour live here. In particular
 * `getSession()`/`setSession()` do **not** — API sessions are a CNR concept and
 * live on {@see \CNIC\CNR\Client} beside the state they read. Do not hoist them,
 * or role credentials ({@see \CNIC\RoleCredentialsInterface}), back up: a
 * capability a brand cannot honour belongs off the shared surface, not on it
 * returning a constant. Full decision record: docs/agents/architecture.md.
 *
 * @psalm-api
 * @package CNIC
 */
abstract class AbstractClient
{
    /**
     * Current module version.
     * Kept in sync automatically by semantic-release — see .releaserc.json.
     */
    private const string VERSION = "30.0.0";

    /**
     * context data for the client
     * @var array<string,mixed>
     */
    protected array $context = [];

    /**
     * Object covering API connection data — the one home for connection
     * configuration; see {@see getSocketConfig()}.
     */
    protected AbstractSocketConfig $socketConfig;

    /**
     * activity flag for debug mode
     */
    protected bool $debugMode = false;

    /**
     * User agent sent with every request. Empty until {@see setUserAgent()} is
     * called; while it is empty {@see getUserAgent()} derives the SDK default.
     *
     * Client state rather than connection configuration: it identifies this
     * library (and the platform embedding it) and is built from {@see VERSION},
     * which semantic-release rewrites in this file.
     */
    protected string $userAgent = "";

    /**
     * logger instance for debug mode
     */
    protected LoggerInterface $logger;

    /**
     * HTTP transport layer
     */
    protected TransportInterface $transport;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->transport = $this->newTransport();
        // Seeds itself: the config's constructor selects LIVE (the default system)
        // and the brand's default cURL options. No client-side URL copy to seed.
        $this->socketConfig = $this->newSocketConfig();
        $this->logger = $this->newLogger(new EchoSink());
    }

    /**
     * The connection configuration this client uses — the accessor that means a new
     * setting needs no forwarder, and the seam that lets configuration be built and
     * asserted without constructing a client.
     *
     * Brands narrow the return type covariantly where they have their own config
     * capabilities — {@see \CNIC\CNR\Client::getSocketConfig()} returns the CNR
     * config, which is the one place the invariant property type is narrowed.
     */
    public function getSocketConfig(): AbstractSocketConfig
    {
        return $this->socketConfig;
    }

    /**
     * Perform API request using the given command.
     *
     * The shared request lifecycle lives in {@see performRequest()}; each brand's
     * public `request()` is a thin wrapper that pins its default `$path` and
     * declares a concrete Response return type. Every brand accepts an optional
     * `$path` appended to the configured base URL to select the endpoint: for
     * IBS/Moniker the path selects the operation (e.g. `Domain/Create`); for CNR
     * it defaults to the single fixed script path (`api/call.cgi`) and rarely
     * varies. The signature is symmetric across all brands.
     *
     * @param array<string, scalar|scalar[]|null> $cmd API command
     * @param string $path path segment appended to the base URL to select the endpoint
     */
    abstract public function request(array $cmd = [], string $path = ""): ResponseInterface;

    /**
     * Shared request lifecycle (template method). Never reimplement it in a brand;
     * vary it through exactly two hooks — {@see buildCommand()} (command
     * flattening) and {@see newResponse()} (covariant Response factory) — plus the
     * {@see newSocketConfig()} subtype, which is where a brand-mandatory cURL
     * option would go. No brand declares one.
     *
     * Brand-specific command rewriting belongs behind `buildCommand()`, not here:
     * CNR's IDN conversion lives in {@see \CNIC\CNR\IDNCommandRewriter}, and a
     * shared step gated by a flag only one brand sets is the shape that replaced.
     *
     * @param array<string, scalar|scalar[]|null> $cmd API command
     * @param string $path path segment appended to the base URL to select the endpoint
     */
    protected function performRequest(array $cmd, string $path = ""): ResponseInterface
    {
        $mycmd = $this->buildCommand($cmd);
        $cfg = ["CONNECTION_URL" => $this->socketConfig->getURL() . $path];
        $data = $this->getPOSTData($mycmd);
        [$raw, $error] = $this->executeCurl($data, $cfg);
        $response = $this->newResponse($raw, $mycmd, $cfg, $error);
        if ($this->debugMode) {
            $this->logger->log($this->getPOSTData($mycmd, true), $response, $error);
        }
        return $response;
    }

    /**
     * Flatten and normalise the given command into wire form.
     * Brand-specific: CNR flattens as-is; IBS injects `ResponseFormat=JSON`.
     *
     * @param array<string, scalar|scalar[]|null> $cmd API command
     * @return array<string, string>
     */
    abstract protected function buildCommand(array $cmd): array;

    /**
     * Instantiate the brand Response for the given raw payload.
     * Return type is covariant so each brand pins its concrete Response.
     *
     * @param array<string, string> $cmd flattened command that produced the response
     * @param array{CONNECTION_URL: string} $cfg connection config used for the request
     * @param string|null $error transport error, if any; non-null means $raw is unusable and the brand's "httperror" template is substituted instead
     */
    abstract protected function newResponse(string $raw, array $cmd, array $cfg, ?string $error = null): ResponseInterface;

    /**
     * Instantiate the SocketConfig for this client.
     * Subclasses return their own SocketConfig subtype.
     */
    abstract protected function newSocketConfig(): AbstractSocketConfig;

    /**
     * Instantiate the HTTP transport for this client. Mirrors
     * {@see newSocketConfig()} — the default is the production cURL transport;
     * override or {@see setTransport()} to inject a test double so the
     * request() lifecycle can run offline.
     */
    protected function newTransport(): TransportInterface
    {
        return new HttpTransport();
    }

    /**
     * Inject a custom HTTP transport (e.g. a record/replay cassette transport
     * for offline tests) in place of the default {@see HttpTransport}.
     * @psalm-api
     */
    public function setTransport(TransportInterface $transport): static
    {
        $this->transport = $transport;
        return $this;
    }

    /**
     * Instantiate the brand's logger, writing to the given sink. Mirrors
     * {@see newTransport()}/{@see newSocketConfig()}, and exists so a sink can be
     * chosen without the brand's Logger class being named at the call site. An
     * override must honour `$sink` — that is what makes {@see setLogSink()} work
     * for a subclass.
     */
    abstract protected function newLogger(LogSinkInterface $sink): LoggerInterface;

    /**
     * Route debug output somewhere other than standard output, keeping this
     * brand's format.
     *
     * This is the seam integrators want: the brand formatter is the part with
     * the logic, the destination is the part that varies per host application.
     * Passing a fresh {@see EchoSink} restores the shipped default, discarding
     * any logger set via {@see setCustomLogger()}.
     */
    public function setLogSink(LogSinkInterface $sink): static
    {
        $this->logger = $this->newLogger($sink);
        return $this;
    }

    /**
     * Set custom logger to use instead of the default one — use this to replace
     * the *format* as well as the destination. Create your own class extending
     * \CNIC\AbstractLogger (format only) or implementing \CNIC\LoggerInterface
     * (format and destination).
     */
    public function setCustomLogger(LoggerInterface $customLogger): static
    {
        $this->logger = $customLogger;
        return $this;
    }

    /**
     * Enable debug output to STDOUT
     */
    public function enableDebugMode(): static
    {
        $this->debugMode = true;
        return $this;
    }

    /**
     * Disable debug output
     */
    public function disableDebugMode(): static
    {
        $this->debugMode = false;
        return $this;
    }

    /**
     * Serialize given command for POST request including connection configuration data
     * @param array<string, string|null> $cmd API command to encode
     */
    public function getPOSTData(array $cmd, bool $maskSecrets = false): string
    {
        return $this->socketConfig->getPOSTData($cmd, $maskSecrets);
    }

    /**
     * Get the API connection url that is currently set
     */
    public function getURL(): string
    {
        return $this->socketConfig->getURL();
    }

    /**
     * Set the request timeout in seconds (default 300).
     *
     * The **only** way to change the timeout: `CURLOPT_TIMEOUT` in the option bag
     * is rejected ({@see setExtraCurlOptions()}) rather than quietly overriding
     * what {@see getSocketTimeout()} reports.
     *
     * @param int $timeoutSeconds 0 carries cURL's meaning — no timeout
     * @throws InvalidConfigurationException on a negative value
     */
    public function setSocketTimeout(int $timeoutSeconds): static
    {
        $this->socketConfig->setSocketTimeout($timeoutSeconds);
        return $this;
    }

    /**
     * Get the request timeout in seconds currently configured.
     */
    public function getSocketTimeout(): int
    {
        return $this->socketConfig->getSocketTimeout();
    }

    /**
     * Set a custom user agent (for platforms that use this SDK)
     * @param array<string> $modules further modules to add to user agent string
     */
    public function setUserAgent(string $label, string $revision, array $modules = []): static
    {
        $mods = $modules === [] ? "" : " " . implode(" ", $modules);
        $this->userAgent = $label . " (" . PHP_OS . "; " . php_uname("m") . "; rv:" . $revision . ")" . $mods . " php-sdk/" . $this->getVersion() . " php/" . implode(".", [PHP_MAJOR_VERSION, PHP_MINOR_VERSION, PHP_RELEASE_VERSION]);
        return $this;
    }

    /**
     * Get the user agent string — the one set via {@see setUserAgent()}, or the
     * SDK default when none was.
     *
     * A pure read — keep it that way. Memoising the default into {@see $userAgent} would
     * make a getter write during a request, and there is nothing worth memoising:
     * the value is a handful of constants and one `php_uname()` call.
     */
    public function getUserAgent(): string
    {
        if ($this->userAgent !== '') {
            return $this->userAgent;
        }
        return "PHP-SDK (" . PHP_OS . "; " . php_uname("m") . "; rv:" . $this->getVersion() . ") php/" . implode(".", [PHP_MAJOR_VERSION, PHP_MINOR_VERSION, PHP_RELEASE_VERSION]);
    }

    /**
     * Merge additional cURL options into the bag, overriding existing values on
     * key collision. Forwards to {@see AbstractSocketConfig::setExtraCurlOptions()},
     * whose docblock carries the detail: what reaches the wire, and the two sets
     * of keys that are refused — the SDK-managed settings
     * ({@see AbstractSocketConfig::MANAGED_OPTIONS}, rejected here and now) and
     * the transport's own ({@see HttpTransport::PROTECTED_OPTIONS}, rejected on
     * the next request).
     * @param array<int, mixed> $opts cURL options keyed by CURLOPT_* constant
     * @throws UnsupportedFeatureException if $opts contains an SDK-managed option
     */
    public function setExtraCurlOptions(array $opts): static
    {
        $this->socketConfig->setExtraCurlOptions($opts);
        return $this;
    }

    /**
     * Restore the cURL option bag to the brand defaults, discarding anything
     * previously handed to {@see setExtraCurlOptions()}. Options only — the proxy
     * and referer are separate state and survive; see
     * {@see AbstractSocketConfig::resetCurlOptions()}.
     */
    public function resetCurlOptions(): static
    {
        $this->socketConfig->resetCurlOptions();
        return $this;
    }

    /**
     * Set proxy to use for API communication
     * @param string $proxy empty string resets it, restoring a direct connection
     */
    public function setProxy(string $proxy = ""): static
    {
        $this->socketConfig->setProxy($proxy);
        return $this;
    }

    /**
     * Get proxy configuration for API communication
     */
    public function getProxy(): ?string
    {
        return $this->socketConfig->getProxy();
    }

    /**
     * Set Referer to use for API communication
     * @param string $referer empty string resets it, so no Referer is sent
     */
    public function setReferer(string $referer = ""): static
    {
        $this->socketConfig->setReferer($referer);
        return $this;
    }

    /**
     * Get Referer configuration for API communication
     */
    public function getReferer(): ?string
    {
        return $this->socketConfig->getReferer();
    }

    /**
     * Get the current module version
     */
    public function getVersion(): string
    {
        return self::VERSION;
    }

    /**
     * Set another connection url to be used for API communication
     */
    public function setURL(string $url): static
    {
        $this->socketConfig->setURL($url);
        return $this;
    }

    /**
     * Set Credentials to be used for API communication.
     *
     * On CNR this **discards any active API session**: `CNR\SocketConfig::setLogin()`/
     * `setPassword()` clear the session id, because a session and a password are
     * alternative credentials on the wire and the newer one is authoritative. The
     * invariant is deliberate and pinned by a test —
     * `CNR\SessionCapable::reuseSession()` depends on it, restoring the login first
     * and the session second. Set the session *after* the credentials, never before.
     * @param string $login empty string resets the stored login
     * @param string $password empty string resets the stored password
     */
    public function setCredentials(string $login = "", string $password = ""): static
    {
        $this->socketConfig->setLogin($login);
        $this->socketConfig->setPassword($password);
        return $this;
    }

    /**
     * Activate High Performance Setup — route requests through the co-located
     * proxy on loopback.
     *
     * Brand-agnostic and therefore shared — the caller supplies the local proxy, so
     * IBS/Moniker may opt in too. It records a flag on the config rather than
     * rewriting the URL, so the selected system survives it; see
     * {@see AbstractSocketConfig::useHighPerformanceConnectionSetup()}.
     */
    public function useHighPerformanceConnectionSetup(): static
    {
        $this->socketConfig->useHighPerformanceConnectionSetup();
        return $this;
    }

    /**
     * Convert domain names to idn + punycode.
     *
     * Brand-agnostic and therefore shared: a thin pass-through to the vendor
     * converter for callers who want to normalise a name explicitly. The automatic
     * rewrite of an outbound *command* is a different thing and is deliberately not
     * here — which parameters carry a domain name is CNR knowledge, and it lives in
     * {@see \CNIC\CNR\IDNCommandRewriter}.
     * @param array<string> $domains list of domain names (or tlds)
     * @return array<int, array{idn: string|false, punycode: string|false}>
     */
    public function IDNConvert(array $domains): array
    {
        /** @var array<int, array{idn: string|false, punycode: string|false}> $result */
        $result = ConverterFactory::convert($domains);
        return $result;
    }

    /**
     * Delegate cURL execution to the transport layer.
     *
     * The configured options are handed over as they are, and beat the
     * transport's own defaults in {@see HttpTransport::post()} (PHP's `+` keeps
     * the left operand on a duplicate key).
     *
     * Do not re-add a per-request options argument here: it would be a route into
     * the option set that skips {@see AbstractSocketConfig::MANAGED_OPTIONS}, and so
     * a way for a subclass to put a second answer behind `getProxy()`. A
     * per-request option belongs on the config before the request, or on a
     * transport the caller drives themselves.
     * @param array{CONNECTION_URL: string} $cfg connection config
     * @return array{0: string, 1: string|null} [rawResponse, errorMessage|null]
     * @throws UnsupportedFeatureException if a transport-owned option was set
     */
    protected function executeCurl(string $postData, array $cfg): array
    {
        return $this->transport->post(
            $cfg["CONNECTION_URL"],
            $postData,
            $this->socketConfig->getSocketTimeout(),
            $this->getUserAgent(),
            $this->socketConfig->getCurlOptions()
        );
    }

    /**
     * Close all cURL connections
     */
    public function close(): void
    {
        $this->transport->close();
    }

    /**
     * Get LIVE system URL.
     *
     * There is deliberately no matching `getOTEUrl()` here: a configuration value
     * no longer needs a hand-written forwarder, so read the OT&E endpoint from
     * {@see getSocketConfig()}.
     */
    public function getLiveUrl(): string
    {
        return $this->socketConfig->getLiveUrl();
    }

    /**
     * Get the API system in use, or null when the configured URL is neither of
     * the brand's two known endpoints.
     *
     * Derived from the URL rather than stored beside it, which is what makes it
     * impossible for the two to disagree — and why the return type is nullable:
     * after a `setURL()` to some other host there is no honest OT&E-or-LIVE answer
     * to give. See {@see AbstractSocketConfig::getSystem()}.
     */
    public function getSystem(): ?System
    {
        return $this->socketConfig->getSystem();
    }

    /**
     * Check whether the OT&E system is in use
     */
    public function isOTE(): bool
    {
        return $this->socketConfig->isOTE();
    }

    /**
     * Set OT&E System for API communication
     */
    public function useOTESystem(): static
    {
        $this->socketConfig->useOTESystem();
        return $this;
    }

    /**
     * Set LIVE System for API communication (this is the default setting)
     */
    public function useLIVESystem(): static
    {
        $this->socketConfig->useLIVESystem();
        return $this;
    }

    /**
     * Set context data for the client
     * @param array<string,mixed> $context
     */
    public function setContext(array $context): static
    {
        $this->context = $context;
        return $this;
    }
}
