<?php

declare(strict_types=1);

/**
 * CNIC
 * Copyright © Team Internet Group PLC
 */

namespace CNIC;

use CNIC\AbstractClient;
use CNIC\CNR\SessionClient;
use CNIC\IBS\SessionClient as IBSSessionClient;
use CNIC\MONIKER\SessionClient as MONIKERSessionClient;
use CNIC\Registrar;

/**
 * ClientFactory
 *
 * @psalm-api
 * @package CNIC
 */
class ClientFactory
{
    /**
     * Returns the registrar-specific Client instance.
     *
     * The factory resolves the registrar identifier to its Client subtype only.
     * All further configuration — credentials, referer, user-agent, proxy,
     * logging and OT&E/sandbox mode — is the caller's responsibility via the
     * client's fluent setters. This keeps the SDK platform-agnostic and
     * transport-faithful: the caller normalizes input (e.g. HTML-entity
     * decoding of WHMCS-stored passwords) before handing it to the client.
     *
     * The declared return type is the shared {@see AbstractClient} base rather
     * than a `SessionClient|IBSSessionClient|MONIKERSessionClient` union. A
     * single common type is what consumers can reason about cleanly: every
     * shared operation (credentials, referer, user-agent, proxy, logging,
     * OT&E/LIVE switching, and the base `request(array $cmd)`) is available
     * directly. Brand-specific capabilities are intentionally *not* on the
     * shared contract and must be reached by narrowing to the concrete type —
     * e.g. CNR session handling (`login()`/`logout()`/`saveSession()`) via
     * `instanceof \CNIC\CNR\SessionClient`, or the IBS/Moniker per-request
     * endpoint path (`request($cmd, $path)`) via `instanceof \CNIC\IBS\Client`.
     * This narrowing is unavoidable for those methods under any common type,
     * since they do not exist on all arms; the union only made the base type
     * itself harder to consume.
     *
     * @param string $registrar Registrar identifier (CNR, CNIC, IBS, MONIKER; case-insensitive)
     * @throws \Exception if the registrar is not supported
     */
    public static function getClient(string $registrar): AbstractClient
    {
        return match (Registrar::tryFrom(strtoupper($registrar))) {
            Registrar::CNR, Registrar::CNIC => new SessionClient(),
            Registrar::IBS                  => new IBSSessionClient(),
            Registrar::MONIKER              => new MONIKERSessionClient(),
            null                            => throw new \Exception("Registrar `{$registrar}` not supported."),
        };
    }
}
