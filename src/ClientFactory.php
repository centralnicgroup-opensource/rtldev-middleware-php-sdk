<?php

declare(strict_types=1);

/**
 * CNIC
 * Copyright © Team Internet Group PLC
 */

namespace CNIC;

use CNIC\CNR\Client as CNRClient;
use CNIC\CNR\SocketConfig as CNRSocketConfig;
use CNIC\IBS\Client as IBSClient;
use CNIC\IBS\SocketConfig as IBSSocketConfig;
use CNIC\MONIKER\Client as MONIKERClient;
use CNIC\MONIKER\SocketConfig as MONIKERSocketConfig;

/**
 * ClientFactory
 *
 * Typed named constructors for each supported registrar brand. Each returns the
 * concrete brand client, fully typed, so every capability that brand supports —
 * shared (credentials, referer, user-agent, proxy, logging, OT&E/LIVE switching,
 * `request($cmd, $path)`) and brand-specific alike — is available directly, with
 * no `assert`/`instanceof` narrowing for the normal path:
 *
 * - {@see cnr()} yields a {@see \CNIC\CNR\Client} with CNR session
 *   handling (`getSession()`/`setSession()`/`login()`/`logout()`/`saveSession()`)
 *   and role credentials (`setRoleCredentials()`, from
 *   {@see \CNIC\RoleCredentialsInterface}).
 * - {@see ibs()}/{@see moniker()} yield the plain brand {@see \CNIC\IBS\Client} /
 *   {@see \CNIC\MONIKER\Client}. Those platforms have no session or
 *   role-credential concept, so those methods are genuinely **absent** — calling
 *   one is a static analysis error at the call site, not a runtime surprise. Keep
 *   it that way: a stub that accepts a session id and discards it is worse than no
 *   method at all.
 *
 * All further configuration — credentials, referer, user-agent, proxy, logging
 * and OT&E/sandbox mode — is the caller's responsibility. This keeps the SDK
 * platform-agnostic and transport-faithful: the caller normalizes input (e.g.
 * HTML-entity decoding of WHMCS-stored passwords) before handing it to the client.
 *
 * There are two routes, and both stay supported. Pass a pre-built brand
 * {@see \CNIC\AbstractSocketConfig} when the connection settings are known up
 * front — the client is then correct the moment it exists, rather than starting on
 * LIVE and being corrected by a setter sequence. Or omit it and use the client's
 * fluent setters, which is the shorter route when the settings are not yet known.
 * The parameter is optional on purpose: `cnr()` with no argument behaves exactly as
 * it always has (RSRMID-2966).
 *
 * @psalm-api
 * @package CNIC
 */
class ClientFactory
{
    /**
     * CentralNic Reseller (CNR, fka RRPproxy) client.
     *
     * @param CNRSocketConfig|null $socketConfig pre-built CNR connection
     *        configuration to adopt; null builds the brand default
     */
    public static function cnr(?CNRSocketConfig $socketConfig = null): CNRClient
    {
        return new CNRClient($socketConfig);
    }

    /**
     * Internet.bs (IBS) client.
     *
     * @param IBSSocketConfig|null $socketConfig pre-built IBS connection
     *        configuration to adopt; null builds the brand default
     */
    public static function ibs(?IBSSocketConfig $socketConfig = null): IBSClient
    {
        return new IBSClient($socketConfig);
    }

    /**
     * Moniker client (same platform as IBS; only the endpoints differ).
     *
     * The parameter is a Moniker config, not an IBS one — the endpoints are the
     * difference between the brands, so an IBS config here is refused at the call
     * site rather than silently pointing a Moniker client at the IBS host.
     *
     * @param MONIKERSocketConfig|null $socketConfig pre-built Moniker connection
     *        configuration to adopt; null builds the brand default
     */
    public static function moniker(?MONIKERSocketConfig $socketConfig = null): MONIKERClient
    {
        return new MONIKERClient($socketConfig);
    }
}
