<?php

declare(strict_types=1);

/**
 * CNIC
 * Copyright © Team Internet Group PLC
 */

namespace CNIC;

use CNIC\CNR\SessionClient;
use CNIC\IBS\SessionClient as IBSSessionClient;
use CNIC\MONIKER\SessionClient as MONIKERSessionClient;

/**
 * ClientFactory
 *
 * Typed named constructors for each supported registrar brand. Each returns the
 * concrete brand SessionClient, fully typed, so every capability that brand
 * supports — shared (credentials, referer, user-agent, proxy, logging,
 * OT&E/LIVE switching, `request($cmd, $path)`) and brand-specific alike — is
 * available directly, with no `assert`/`instanceof` narrowing for the normal
 * path:
 *
 * - {@see cnr()} yields a {@see \CNIC\CNR\SessionClient} with CNR session
 *   handling (`login()`/`logout()`/`saveSession()`) and role credentials
 *   (`setRoleCredentials()`, from {@see \CNIC\RoleCredentialsInterface}).
 * - {@see ibs()}/{@see moniker()} yield the IBS/Moniker SessionClient; those
 *   platforms have no session or role-credential concept, so those methods are
 *   simply absent rather than present-and-throwing.
 *
 * All further configuration — credentials, referer, user-agent, proxy, logging
 * and OT&E/sandbox mode — is the caller's responsibility via the client's fluent
 * setters. This keeps the SDK platform-agnostic and transport-faithful: the
 * caller normalizes input (e.g. HTML-entity decoding of WHMCS-stored passwords)
 * before handing it to the client.
 *
 * @psalm-api
 * @package CNIC
 */
class ClientFactory
{
    /**
     * CentralNic Reseller (CNR, fka RRPproxy) client.
     */
    public static function cnr(): SessionClient
    {
        return new SessionClient();
    }

    /**
     * Internet.bs (IBS) client.
     */
    public static function ibs(): IBSSessionClient
    {
        return new IBSSessionClient();
    }

    /**
     * Moniker client (same platform as IBS; only the endpoints differ).
     */
    public static function moniker(): MONIKERSessionClient
    {
        return new MONIKERSessionClient();
    }
}
