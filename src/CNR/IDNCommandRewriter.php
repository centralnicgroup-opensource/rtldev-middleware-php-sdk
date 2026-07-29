<?php

declare(strict_types=1);

/**
 * CNIC\CNR
 * Copyright © Team Internet Group PLC
 */

namespace CNIC\CNR;

use CNIC\IDNA\Factory\ConverterFactory;

/**
 * Rewrites the IDN-bearing parameters of a CNR API command to punycode.
 *
 * ## Why this is a module and not a client method (RSRMID-2922)
 *
 * These rules are CNR domain knowledge: which parameter names carry a domain
 * name, that `OBJECTID` is a pattern parameter whose content depends on
 * `OBJECTCLASS`, and that an already-ASCII value must be left alone. Until v24
 * they sat on the shared {@see \CNIC\AbstractClient} as the largest method in
 * that class, switched on by a `needsIDNConvert` flag that only
 * {@see SocketConfig} set true — brand behaviour on a base shared with IBS and
 * Moniker, gated by a flag whose only job was to disable it for two brands out
 * of three. That is the pattern RSRMID-2911 removed for role credentials and
 * RSRMID-2915 removed for the IBS cURL default; this is the same move.
 *
 * It is also what made the rules untestable: with no surface of their own, the
 * only test reached past the client with `ReflectionMethod::setAccessible()`.
 * They are now called from CNR's {@see Client::buildCommand()} hook — the brand
 * variation point the request template method already provides — and asserted
 * directly in `tests/CNR/IDNCommandRewriterTest.php`.
 *
 * The conversion itself is not CNR-specific and stays where it was: the vendor
 * `centralnic-reseller/idn-converter`, also reachable for callers as the public
 * {@see \CNIC\AbstractClient::IDNConvert()}.
 *
 * @psalm-api
 * @package CNIC\CNR
 */
final class IDNCommandRewriter
{
    /**
     * Values consisting only of letters, digits, dots and hyphens are already on
     * the wire alphabet and are passed through untouched.
     */
    private const string ASCII_PATTERN = "/^[a-zA-Z0-9\.-]+$/i";

    /**
     * Parameters carrying a domain name that the API does *not* convert itself.
     * `DOMAIN` is absent on purpose — the API converts that one server-side.
     * `NS`/`NS<n>` is the short nameserver form (RSRBE-7149).
     */
    private const string KEY_PATTERN = "/^(PARENTDOMAIN|NAMESERVER|NS|DNSZONE)([0-9]*)$/i";

    /**
     * The `OBJECTCLASS` values for which `OBJECTID` holds a domain-like name.
     * Anything else (a contact handle, a user id, ...) must not be converted.
     */
    private const string OBJECTCLASS_PATTERN = "/^(DOMAIN(APPLICATION|BLOCKING)?|NAMESERVER|NS|DNSZONE)$/i";

    /**
     * Convert the IDN-bearing values of a flattened CNR command to punycode.
     *
     * Values are rewritten in place, so key order survives — this runs after
     * {@see \CNIC\CommandFormatter::flattenCommand()} has applied the priority
     * sort, and the wire output must not depend on it.
     *
     * Without `ext-intl` the command is returned verbatim: the converter needs
     * `idn_to_ascii()`, and an SDK that cannot convert must still be able to send
     * the ASCII commands that make up the vast majority of traffic.
     *
     * @param array<string, string> $cmd flattened API command
     * @return array<string, string> the command with IDN values converted to punycode
     */
    public static function rewrite(array $cmd): array
    {
        if (!function_exists("idn_to_ascii")) {
            return $cmd;
        }

        $objectClass = $cmd["OBJECTCLASS"] ?? null;
        $toconvert = [];
        foreach ($cmd as $key => $val) {
            if (self::carriesDomainName($key, $objectClass) && !(bool)preg_match(self::ASCII_PATTERN, $val)) {
                $toconvert[$key] = $val;
            }
        }
        if ($toconvert === []) {
            return $cmd;
        }

        // The converter preserves the keys of the array it is handed, so the
        // results come back keyed by command key. Handing it a list and pairing
        // the results back up by position — which is what this did on
        // AbstractClient — needed a second parallel array and an invariant
        // between the two.
        /** @var array<string, array{idn: string|false, punycode: string|false}> $results */
        $results = ConverterFactory::convert($toconvert);
        foreach ($results as $key => $row) {
            $cmd[$key] = (string)$row["punycode"];
        }
        return $cmd;
    }

    /**
     * Whether the given command key holds a domain name that needs converting.
     *
     * `OBJECTID` is the one key whose answer depends on another parameter: it is
     * a pattern parameter in the CNR API and does not accept IDNs, but what it
     * matches against is whatever `OBJECTCLASS` selects, so it is only a domain
     * name for the domain-like classes (RSRTPM-3167).
     *
     * @param string|null $objectClass the command's OBJECTCLASS, null when absent
     */
    private static function carriesDomainName(string $key, ?string $objectClass): bool
    {
        if ((bool)preg_match(self::KEY_PATTERN, $key)) {
            return true;
        }
        return $key === "OBJECTID"
            && $objectClass !== null
            && (bool)preg_match(self::OBJECTCLASS_PATTERN, $objectClass);
    }
}
