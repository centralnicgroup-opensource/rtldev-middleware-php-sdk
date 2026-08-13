<?php

declare(strict_types=1);

/**
 * CNIC\Exception
 * Copyright © Team Internet Group PLC
 */

namespace CNIC\Exception;

/**
 * Thrown when a capability is not available on the current platform or response.
 *
 * Some operations exist on the shared contract but are not offered by every
 * brand — e.g. the IBS/Moniker platform has no API session, user roles, high
 * performance connection setup, queue/runtime metrics, temporary-error or
 * pending states, and no server-side list-hash. Calling such a method raises
 * this exception instead of returning a misleading value.
 *
 * It also covers the handful of "an option/header the SDK or transport already
 * owns cannot be overridden through a generic bag" rejections — those are the
 * situations a caller can actually act on, and they carry structured context
 * (which key(s) were rejected, what replaces them, which class owns the
 * rejection) alongside the message, via the named constructors below
 * ({@see transportOwnedCurlOptions()}, {@see sdkManagedCurlOptions()},
 * {@see transportOwnedHeader()}) and read back through the accessors. Message
 * and context are composed together, on purpose: a message that describes one
 * thing while the accessors describe another is exactly the drift this
 * structure exists to remove.
 *
 * Not every throw site has something actionable to hand back. The config-type
 * guard in {@see \CNIC\CNR\Client::getSocketConfig()} deliberately keeps using
 * the plain constructor: it protects against a subclass supplying the wrong
 * `SocketConfig` type, which is unreachable in practice and, if it were ever
 * reached, is a programming error with nothing a caller could act on — there is
 * no replacement setter or rejected key to report.
 *
 * @psalm-api
 * @package CNIC\Exception
 */
class UnsupportedFeatureException extends CnicException
{
    /**
     * cURL options rejected from a call, keyed by the cURL constant and valued
     * with its constant name. Empty for a plainly-constructed instance, and for
     * {@see transportOwnedHeader()}, which rejects a header rather than an option.
     * @var array<int, string>
     */
    private array $rejectedCurlOptions = [];

    /**
     * The replacement setter for each rejected cURL option that has one, keyed
     * by the cURL constant and valued with `"Fqcn::setter()"`. Empty when the
     * rejected options have no replacement (a transport-owned option is simply
     * dropped, not redirected) or for a plainly-constructed instance.
     * @var array<int, string>
     */
    private array $replacementSetters = [];

    /**
     * The rejected header name — already lower-cased, as the throw site holds
     * it — or null when this instance does not describe a header rejection.
     */
    private ?string $rejectedHeaderName = null;

    /**
     * The class that owns the rejected option(s)/header, or null when this
     * instance carries no structured context.
     * @var class-string|null
     */
    private ?string $owningClass = null;

    /**
     * Build the exception raised when {@see \CNIC\HttpTransport::post()} refuses
     * one or more transport-owned cURL options
     * ({@see \CNIC\HttpTransport::PROTECTED_OPTIONS}).
     *
     * A protected option has no replacement setter — it must simply be dropped
     * from the caller's bag — so {@see getReplacementSetters()} answers empty for
     * an instance built here.
     * @param array<int, string> $rejected a slice of {@see \CNIC\HttpTransport::PROTECTED_OPTIONS}
     * @param class-string $owningClass
     */
    public static function transportOwnedCurlOptions(array $rejected, string $owningClass): self
    {
        $e = new self(
            "cURL option(s) owned by " . $owningClass . " cannot be overridden: " . implode(", ", array_values($rejected))
            . ". They define the request envelope the response parser depends on, or the TLS verification"
            . " posture; remove them from the option bag."
        );
        $e->rejectedCurlOptions = $rejected;
        $e->owningClass = $owningClass;
        return $e;
    }

    /**
     * Build the exception raised when {@see \CNIC\AbstractSocketConfig::setExtraCurlOptions()}
     * refuses one or more SDK-managed cURL options
     * ({@see \CNIC\AbstractSocketConfig::MANAGED_OPTIONS}).
     *
     * Note the current message does not name $owningClass — the owner of
     * *setExtraCurlOptions()* itself is not the point here, each managed option
     * already names its own owner in $rejected; $owningClass is accessor-only.
     * @param array<int, array{option: string, owner: class-string, setter: string}> $rejected
     *        a slice of {@see \CNIC\AbstractSocketConfig::MANAGED_OPTIONS}
     * @param class-string $owningClass
     */
    public static function sdkManagedCurlOptions(array $rejected, string $owningClass): self
    {
        $named = array_map(
            static fn(array $entry): string => $entry["option"]
                . " (use " . $entry["owner"] . "::" . $entry["setter"] . ")",
            array_values($rejected)
        );
        $e = new self(
            "cURL option(s) the SDK models as configuration cannot be set through the option bag: "
            . implode(", ", $named)
            . ". Setting one both ways would leave the getter and the wire disagreeing; use the setter"
            . " named above, which is the single home for that value."
        );
        $rejectedCurlOptions = [];
        $replacementSetters = [];
        foreach ($rejected as $opt => $entry) {
            $rejectedCurlOptions[$opt] = $entry["option"];
            $replacementSetters[$opt] = $entry["owner"] . "::" . $entry["setter"];
        }
        $e->rejectedCurlOptions = $rejectedCurlOptions;
        $e->replacementSetters = $replacementSetters;
        $e->owningClass = $owningClass;
        return $e;
    }

    /**
     * Build the exception raised when {@see \CNIC\HttpTransport::appendHeaders()}
     * refuses a caller header line that restates one the transport owns.
     * @param class-string $owningClass
     */
    public static function transportOwnedHeader(string $headerName, string $owningClass): self
    {
        $e = new self(
            "HTTP header(s) owned by " . $owningClass . " cannot be overridden: " . $headerName
            . ". Content-Type/Content-Length describe the POST body and Connection follows from"
            . " the reused handle; add your own headers instead of restating these."
        );
        $e->rejectedHeaderName = $headerName;
        $e->owningClass = $owningClass;
        return $e;
    }

    /**
     * The cURL options this exception rejected, keyed by the cURL constant and
     * valued with its constant name. Empty for a plainly-constructed instance.
     *
     * The actionable use: `array_diff_key($yourOptions, $e->getRejectedCurlOptions())`
     * strips exactly the refused keys from the bag you tried to send, so the
     * call can be retried with what is left.
     * @return array<int, string>
     * @psalm-api
     */
    public function getRejectedCurlOptions(): array
    {
        return $this->rejectedCurlOptions;
    }

    /**
     * The replacement setter for each rejected cURL option that has one, keyed
     * by the cURL constant and valued with `"Fqcn::setter()"`. Empty when the
     * rejected options have no replacement, or for a plainly-constructed
     * instance.
     * @return array<int, string>
     * @psalm-api
     */
    public function getReplacementSetters(): array
    {
        return $this->replacementSetters;
    }

    /**
     * The class that owns the rejected option(s)/header, or null when this
     * instance carries no structured context.
     * @return class-string|null
     * @psalm-api
     */
    public function getOwningClass(): ?string
    {
        return $this->owningClass;
    }

    /**
     * The rejected header name, already lower-cased, or null when this instance
     * does not describe a header rejection.
     * @psalm-api
     */
    public function getRejectedHeaderName(): ?string
    {
        return $this->rejectedHeaderName;
    }
}
