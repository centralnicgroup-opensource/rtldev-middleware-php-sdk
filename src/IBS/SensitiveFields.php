<?php

declare(strict_types=1);

/**
 * CNIC\IBS
 * Copyright © Team Internet Group PLC
 */

namespace CNIC\IBS;

/**
 * IBS SensitiveFields
 *
 * The single declaration of which IBS command keys carry sensitive data
 * (account password, domain transfer authorization code). {@see SocketConfig}
 * and {@see Response} both read {@see KEYS} for their `$sensitiveFields`
 * default instead of each hard-coding the same literal array, so the two
 * lists can no longer silently diverge. MONIKER inherits this via
 * `MONIKER\SocketConfig extends IBS\SocketConfig` and reuses `IBS\Response`
 * directly, so it needs no declaration of its own.
 *
 * Not part of the SDK's public surface — an internal anti-drift holder, not a
 * capability a consumer of the IBS API has any use for.
 *
 * @internal
 * @package CNIC\IBS
 */
final class SensitiveFields
{
    /**
     * IBS carries sensitive data under lower-/camel-case command keys.
     * @var string[]
     */
    public const array KEYS = ["password", "transferAuthInfo"];
}
