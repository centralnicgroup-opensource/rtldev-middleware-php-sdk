<?php

declare(strict_types=1);

/**
 * CNIC
 * Copyright © Team Internet Group PLC
 */

namespace CNIC;

use CNIC\Exception\InvalidConfigurationException;
use CNIC\Exception\UnsupportedFeatureException;

/**
 * Shared base for all registrar SocketConfig implementations, and **the one home
 * for connection configuration**.
 *
 * Concrete subclasses provide getPOSTDataParams() and their own $parameters array
 * shaped to the API they target.
 *
 * **This class owns the connection** — where to connect (`$url`, the endpoints,
 * the high-performance route), how to authenticate (login/password, plus CNR's
 * session on its own subclass), and how the transport should behave (timeout,
 * proxy, referer, extra cURL options). {@see AbstractClient} owns **client
 * behaviour** — logging, response context, the transport instance, and the SDK's
 * own identity. Its configuration methods are forwarders to this object, reading
 * and writing the state below rather than copies of it. Do not add a client-side
 * copy of anything declared here: two homes for one value is the defect class
 * this split exists to prevent, and it is guarded by
 * tests/ClientConfigSeamTest.php.
 *
 * Two invariants keep "one answer" true, and both are enforced rather than
 * documented:
 * - **The system is derived from the URL, never stored** ({@see getSystem()}).
 *   There is no flag left to disagree with the endpoint in use.
 * - **A value the SDK models as configuration cannot also be set through the
 *   cURL bag** ({@see MANAGED_OPTIONS}) — passing one raises rather than becoming
 *   a second answer the getter cannot see.
 *
 * Full decision record: docs/agents/architecture.md.
 *
 * @psalm-api
 * @package CNIC
 */
abstract class AbstractSocketConfig
{
    /**
     * cURL options the SDK models as first-class configuration, each with the
     * setter that owns it. Passing one to {@see setExtraCurlOptions()} raises
     * {@see UnsupportedFeatureException} naming the replacement.
     *
     * They are refused not for the transport's sake — {@see HttpTransport} accepts
     * all four, and a caller driving it directly still may — but because each
     * already has a home, and letting the bag carry a second value would put two
     * answers behind one question: `getProxy()` reporting what `setProxy()` stored
     * while the wire carried the bag's value. Throw, naming constant and setter;
     * never silently pick a winner.
     *
     * Each entry carries the **class** that owns the setter, and the message
     * renders it, because the four are not all on one object: `setUserAgent()`
     * lives on {@see AbstractClient} (the SDK's identity is versioned with that
     * class), so a bare `setUserAgent()` would point a caller holding only the
     * config at a method it cannot reach.
     *
     * Rejection is **eager** here, unlike {@see HttpTransport::PROTECTED_OPTIONS}
     * which is checked on the next request: the config knows immediately, and the
     * error belongs where the mistake is. Options the SDK does *not* model
     * (CURLOPT_CONNECTTIMEOUT, CURLOPT_IPRESOLVE, ...) stay caller-owned and pass
     * through untouched — the bag is for what the SDK has no opinion about.
     *
     * Keep the list exactly the options that have their own setter: widening it
     * takes away legitimate tuning, narrowing it re-opens a second home. Pinned by
     * AbstractClientCurlOptionsTest::testManagedOptionsAreExactlyTheOnesWithTheirOwnSetter().
     *
     * @var array<int, array{option: string, owner: class-string, setter: string}>
     */
    public const array MANAGED_OPTIONS = [
        CURLOPT_TIMEOUT => [
            "option" => "CURLOPT_TIMEOUT",
            "owner"  => self::class,
            "setter" => "setSocketTimeout()",
        ],
        CURLOPT_USERAGENT => [
            "option" => "CURLOPT_USERAGENT",
            "owner"  => AbstractClient::class,
            "setter" => "setUserAgent()",
        ],
        CURLOPT_PROXY => [
            "option" => "CURLOPT_PROXY",
            "owner"  => self::class,
            "setter" => "setProxy()",
        ],
        CURLOPT_REFERER => [
            "option" => "CURLOPT_REFERER",
            "owner"  => self::class,
            "setter" => "setReferer()",
        ],
    ];

    /**
     * account name
     */
    protected string $login = "";

    /**
     * account password
     */
    protected string $password = "";

    /**
     * API OT&E endpoint URL
     */
    protected string $oteUrl = "";

    /**
     * API LIVE endpoint URL
     */
    protected string $liveUrl = "";

    /**
     * The endpoint requests are sent to — one of {@see $oteUrl}/{@see $liveUrl}
     * when a system was selected, or whatever {@see setURL()} was handed.
     *
     * The **only** stored URL/system state: which system this is gets derived
     * from it ({@see getSystem()}) rather than tracked alongside it. Seeded to
     * {@see $liveUrl} by the constructor, LIVE being the default.
     */
    protected string $url = "";

    /**
     * Whether requests are routed through the co-located high-performance proxy
     * on loopback ({@see useHighPerformanceConnectionSetup()}).
     *
     * A flag applied by {@see getURL()} rather than a rewrite of {@see $url}, so
     * the selected system survives it: routing through a local proxy says nothing
     * about which system sits behind that proxy. Do not make it eager again.
     */
    protected bool $highPerformance = false;

    /**
     * Proxy for API communication, or null for a direct connection.
     *
     * Deliberately real state rather than a {@see $curlOptions} key, so that
     * {@see resetCurlOptions()} — whose job is restoring *option* defaults —
     * cannot forget the proxy the caller configured.
     */
    protected ?string $proxy = null;

    /**
     * Referer sent with API requests, or null to send none. Real state for the
     * same reason as {@see $proxy}.
     */
    protected ?string $referer = null;

    /**
     * Caller-supplied cURL options, over and above what the transport and the
     * dedicated setters above provide. Seeded from {@see getDefaultCurlOpts()}
     * by the constructor, mutated by {@see setExtraCurlOptions()} and restored to
     * those defaults by {@see resetCurlOptions()}.
     * @var array<int, mixed>
     */
    protected array $curlOptions = [];

    /**
     * API socket timeout in seconds
     */
    protected int $socketTimeout = 300;

    /**
     * Command parameter keys whose values carry sensitive data (account
     * password, domain authorization code, ...) and must be masked in the
     * "secured" POST body used for debug logging. Matching is case-insensitive
     * (see maskSensitiveCommand()), so only the names matter, not their casing.
     * Brand subclasses declare the keys their API uses, sourced from a single
     * per-brand constant (e.g. {@see \CNIC\CNR\SensitiveFields::KEYS}) shared
     * with the corresponding Response class, so the debug mask and the
     * stored-command mask cover the same fields by construction.
     * @var string[]
     */
    protected array $sensitiveFields = [];

    /**
     * Seed the runtime state that depends on the brand's property defaults.
     *
     * Both seeds have to happen after the subclass's property initialisers, which
     * is why they are here and not inline defaults: {@see $url} starts at the
     * brand's {@see $liveUrl} (LIVE is the default system) and {@see $curlOptions}
     * at the brand's {@see getDefaultCurlOpts()}.
     */
    public function __construct()
    {
        $this->url = $this->liveUrl;
        $this->curlOptions = $this->getDefaultCurlOpts();
    }

    /**
     * Set account name to use
     */
    public function setLogin(string $login): static
    {
        $this->login = $login;
        return $this;
    }

    /**
     * Get current login
     */
    public function getLogin(): string
    {
        return $this->login;
    }

    /**
     * Set account password to use
     */
    public function setPassword(string $password): static
    {
        $this->password = $password;
        return $this;
    }

    /**
     * Get the endpoint API requests are sent to — the **effective** URL.
     *
     * The stored {@see $url}, with the loopback rewrite applied when
     * high-performance mode is on. That rewrite is resolved here on every read
     * rather than burnt into {@see $url}, so switching systems afterwards keeps
     * it and {@see getSystem()} still knows which system was selected.
     *
     * Note the asymmetry with {@see setURL()}, which is deliberate: this returns
     * where requests *go*, while `setURL()`/{@see getSystem()}/{@see isOTE()}
     * operate on which endpoint was *selected*. The two differ only under
     * high-performance routing, and only in that direction — so do not round-trip
     * one into the other: `setURL($cfg->getURL())` burns the loopback rewrite into
     * the selection and loses the system, which is the very drift this class
     * exists to prevent.
     */
    public function getURL(): string
    {
        return $this->highPerformance ? self::toLoopback($this->url) : $this->url;
    }

    /**
     * Set another connection url to be used for API communication.
     *
     * This replaces the endpoint selection wholesale, so a URL that is neither
     * {@see $oteUrl} nor {@see $liveUrl} leaves {@see getSystem()} answering
     * `null` — the SDK cannot know which system an arbitrary host fronts, and
     * answering with the last selection is how a stored flag comes to disagree
     * with the URL in use.
     */
    public function setURL(string $url): static
    {
        $this->url = $url;
        return $this;
    }

    /**
     * Get OT&E endpoint URL
     */
    public function getOTEUrl(): string
    {
        return $this->oteUrl;
    }

    /**
     * Get LIVE endpoint URL
     */
    public function getLiveUrl(): string
    {
        return $this->liveUrl;
    }

    /**
     * Get the API system currently in use, or null when the configured URL is
     * neither of the brand's two known endpoints.
     *
     * Derived from {@see $url}, never stored — a stored copy is a second answer
     * waiting to contradict the first. The `null` case is honest rather than
     * defensive: after `setURL("https://staging.example/")` the client is on a
     * system the SDK has no name for, and a caller branching on OT&E-vs-LIVE needs
     * to see that rather than be told "LIVE".
     */
    public function getSystem(): ?System
    {
        if ($this->url === $this->oteUrl) {
            return System::OTE;
        }
        if ($this->url === $this->liveUrl) {
            return System::LIVE;
        }
        return null;
    }

    /**
     * Check whether the OT&E endpoint is in use
     */
    public function isOTE(): bool
    {
        return $this->getSystem() === System::OTE;
    }

    /**
     * Select the OT&E system for API communication
     */
    public function useOTESystem(): static
    {
        return $this->setURL($this->oteUrl);
    }

    /**
     * Select the LIVE system for API communication (the default)
     */
    public function useLIVESystem(): static
    {
        return $this->setURL($this->liveUrl);
    }

    /**
     * Route API requests through the co-located high-performance proxy on
     * loopback.
     *
     * Recorded as a flag and applied by {@see getURL()} on every read, not by
     * rewriting {@see $url} once: an eager rewrite silently costs the caller
     * `isOTE()`/`getSystem()`. It therefore also survives a later
     * `useOTESystem()`/`useLIVESystem()`/`setURL()` — high-performance routing is
     * a property of *how* to reach the endpoint, not of *which* endpoint. There is
     * no disable method; construct a fresh client if you need one without it.
     */
    public function useHighPerformanceConnectionSetup(): static
    {
        $this->highPerformance = true;
        return $this;
    }

    /**
     * Whether high-performance (loopback proxy) routing is switched on
     */
    public function usesHighPerformanceConnectionSetup(): bool
    {
        return $this->highPerformance;
    }

    /**
     * Rewrite a URL to target the co-located high-performance proxy on loopback.
     *
     * The https->http downgrade is deliberate and safe: the request never leaves
     * the host — it targets a trusted local socket — so credentials in the POST
     * body are not exposed on the wire. Rebuilt from the URL components so only
     * the scheme and host are swapped; a blind str_replace would also clobber a
     * hostname recurring in the path or query. A URL with no host is returned
     * unchanged, there being nothing to redirect.
     */
    private static function toLoopback(string $url): string
    {
        $parts = parse_url($url);
        if (!isset($parts["host"]) || $parts["host"] === '') {
            return $url;
        }
        return "http://127.0.0.1"
            . (isset($parts["port"]) ? ":" . $parts["port"] : "")
            . ($parts["path"] ?? "")
            . (isset($parts["query"]) ? "?" . $parts["query"] : "");
    }

    /**
     * Set the proxy to use for API communication
     * @param string $proxy empty string resets it, restoring a direct connection
     */
    public function setProxy(string $proxy = ""): static
    {
        $this->proxy = $proxy === '' ? null : $proxy;
        return $this;
    }

    /**
     * Get the proxy configured for API communication, or null for a direct
     * connection
     */
    public function getProxy(): ?string
    {
        return $this->proxy;
    }

    /**
     * Set the Referer to send with API requests
     * @param string $referer empty string resets it, so no Referer is sent
     */
    public function setReferer(string $referer = ""): static
    {
        $this->referer = $referer === '' ? null : $referer;
        return $this;
    }

    /**
     * Get the Referer sent with API requests, or null when none is sent
     */
    public function getReferer(): ?string
    {
        return $this->referer;
    }

    /**
     * Brand-default cURL options, used to seed and to reset {@see $curlOptions}.
     *
     * **No brand overrides this, and new overrides should be resisted** — transport
     * tuning is the caller's decision via {@see setExtraCurlOptions()}, and a brand
     * default that papers over one environment's networking does not qualify. The
     * bar is a genuinely protocol-mandatory option. The hook is kept because it is
     * the seam {@see resetCurlOptions()} is defined in terms of: reset restores
     * these defaults rather than blindly wiping the bag. Guarded by
     * ClientConfigSeamTest::testNoBrandOverridesTheDefaultCurlOptsHook().
     * @return array<int, mixed>
     */
    protected function getDefaultCurlOpts(): array
    {
        return [];
    }

    /**
     * Merge additional cURL options into the bag, overriding existing values on
     * key collision (including brand defaults). Use {@see resetCurlOptions()} to
     * restore the brand defaults afterwards.
     *
     * What lands here reaches the wire: {@see HttpTransport::post()} applies the
     * bag *over* its own defaults, so an option of yours wins on collision. Two
     * sets of keys are refused rather than allowed to become a second answer or a
     * silent loser:
     * - {@see MANAGED_OPTIONS} — CURLOPT_TIMEOUT, USERAGENT, PROXY and REFERER
     *   each already have a setter that owns them. Rejected here, immediately,
     *   naming the setter to use instead.
     * - {@see HttpTransport::PROTECTED_OPTIONS} — the request envelope
     *   (CURLOPT_URL/POST/POSTFIELDS/RETURNTRANSFER/HEADER) and the TLS
     *   verification posture (SSL_VERIFYPEER/SSL_VERIFYHOST). Those belong to the
     *   transport, which rejects them on the next request; the config does not
     *   pre-empt that check, because the transport is injectable
     *   ({@see TransportInterface}) and which options it owns is its own business.
     *
     * CURLOPT_HTTPHEADER is additive at the transport: your lines are appended,
     * and restating one of the transport's own throws.
     * @param array<int, mixed> $opts cURL options keyed by CURLOPT_* constant
     * @throws UnsupportedFeatureException if $opts contains an SDK-managed option
     */
    public function setExtraCurlOptions(array $opts): static
    {
        self::rejectManagedOptions($opts);
        $this->curlOptions = $opts + $this->curlOptions;
        return $this;
    }

    /**
     * Restore the cURL option bag to the brand defaults
     * ({@see getDefaultCurlOpts()}), discarding anything previously handed to
     * {@see setExtraCurlOptions()}.
     *
     * Scope note: **options only**. The proxy and the referer are not bag keys
     * ({@see $proxy}/{@see $referer}), so this does not forget them — reset those
     * explicitly with `setProxy()`/`setReferer()` if that is what you meant.
     *
     * It restores the defaults rather than clearing the bag, so a brand default
     * would survive; no brand declares one, so today this empties the bag.
     */
    public function resetCurlOptions(): static
    {
        $this->curlOptions = $this->getDefaultCurlOpts();
        return $this;
    }

    /**
     * The cURL options to hand the transport for a request: the dedicated
     * proxy/referer state plus the caller's bag.
     *
     * The dedicated state goes on the **left** of the union — the side PHP's `+`
     * keeps on a duplicate key — so what {@see getProxy()} reports is what goes on
     * the wire, structurally rather than by convention. Do not "simplify" the order
     * on the grounds that {@see setExtraCurlOptions()} already refuses those two
     * keys: that guard is not the only writer of {@see $curlOptions}, since
     * {@see getDefaultCurlOpts()} seeds it through the constructor and
     * {@see resetCurlOptions()} re-seeds it, neither passing through the guard. A
     * brand default of CURLOPT_PROXY would then leave the getter reporting the
     * setter's value while the request used the default. Pinned by
     * AbstractClientConfigDriftTest::testDedicatedProxyStateBeatsABrandDefaultOnTheWire().
     * @return array<int, mixed>
     */
    public function getCurlOptions(): array
    {
        $dedicated = [];
        if ($this->proxy !== null) {
            $dedicated[CURLOPT_PROXY] = $this->proxy;
        }
        if ($this->referer !== null) {
            $dedicated[CURLOPT_REFERER] = $this->referer;
        }
        return $dedicated + $this->curlOptions;
    }

    /**
     * Fail loudly when the caller routes an SDK-managed setting through the
     * option bag. See {@see MANAGED_OPTIONS} for why picking a winner silently is
     * not an option.
     *
     * @param array<int, mixed> $opts
     * @throws UnsupportedFeatureException
     */
    private static function rejectManagedOptions(array $opts): void
    {
        $rejected = array_intersect_key(self::MANAGED_OPTIONS, $opts);
        if ($rejected === []) {
            return;
        }
        throw UnsupportedFeatureException::sdkManagedCurlOptions($rejected, self::class);
    }

    /**
     * Get socket timeout in seconds
     */
    public function getSocketTimeout(): int
    {
        return $this->socketTimeout;
    }

    /**
     * Set the socket timeout in seconds — the ceiling on a whole API request.
     *
     * The single home for the request timeout: the bag rejects CURLOPT_TIMEOUT
     * ({@see MANAGED_OPTIONS}), so this is the only way in and
     * {@see getSocketTimeout()} the only answer.
     * {@see AbstractClient::setSocketTimeout()} forwards here.
     *
     * 0 carries cURL's meaning — no timeout — and is passed through unchanged.
     * A negative value is rejected rather than forwarded: cURL refuses it by
     * returning `false` from `curl_setopt()`, whose result `curl_setopt_array()`
     * does not surface, so forwarding it would drop the setting with no signal.
     * @param int $timeoutSeconds 0 carries cURL's meaning — no timeout
     * @throws InvalidConfigurationException on a negative value
     */
    public function setSocketTimeout(int $timeoutSeconds): static
    {
        if ($timeoutSeconds < 0) {
            throw new InvalidConfigurationException(
                "Socket timeout must be 0 (no timeout) or a positive number of seconds, got {$timeoutSeconds}."
            );
        }
        $this->socketTimeout = $timeoutSeconds;
        return $this;
    }

    /**
     * Mask the values of the brand's sensitive command keys (see
     * $sensitiveFields) so command-level secrets — e.g. a domain transfer
     * authorization code — never reach the debug log in cleartext. Delegates the
     * actual matching/masking to {@see CommandRedactor::redact()}, which is
     * shared with {@see AbstractResponse::sanitizeCommand()}. Matching is
     * case-insensitive to stay robust against casing differences between what a
     * brand documents and what it actually sends. `null` values are left
     * untouched (they are dropped from the request, not logged).
     * @param array<string, string|null> $command API Command to mask
     * @return array<string, string|null>
     */
    protected function maskSensitiveCommand(array $command): array
    {
        return CommandRedactor::redact($command, $this->sensitiveFields);
    }

    /**
     * Get POST data container of connection data
     * @param array<string, string|null> $command API Command to request
     * @return array<string, string|null>
     */
    abstract protected function getPOSTDataParams(array $command, bool $maskSecrets): array;

    /**
     * Create POST data string out of connection data.
     *
     * Purely the encoding step: every parameter, including brand-specific ones,
     * comes from {@see getPOSTDataParams()}. Do not reintroduce brand knowledge at
     * this level — CNR's `persistent=1` is appended by its own
     * getPOSTDataParams(), which is why this method knows nothing brand-specific.
     * @param array<string, string|null> $command API Command to request
     * @return string POST data string
     */
    public function getPOSTData(array $command = [], bool $maskSecrets = false): string
    {
        return http_build_query($this->getPOSTDataParams($command, $maskSecrets));
    }
}
