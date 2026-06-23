<?php

declare(strict_types=1);

namespace CNIC;

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
     * @param string $registrar Registrar identifier (CNR, CNIC, IBS, MONIKER; case-insensitive)
     * @throws \Exception if the registrar is not supported
     */
    public static function getClient(string $registrar): SessionClient|IBSSessionClient|MONIKERSessionClient
    {
        return match (Registrar::tryFrom(strtoupper($registrar))) {
            Registrar::CNR, Registrar::CNIC => new SessionClient(),
            Registrar::IBS                  => new IBSSessionClient(),
            Registrar::MONIKER              => new MONIKERSessionClient(),
            null                            => throw new \Exception("Registrar `{$registrar}` not supported."),
        };
    }
}
