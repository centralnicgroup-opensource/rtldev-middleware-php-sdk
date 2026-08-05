<?php

declare(strict_types=1);

/**
 * CNIC\CNR
 * Copyright © Team Internet Group PLC
 */

namespace CNIC\CNR;

/**
 * CNR SensitiveFields
 *
 * The single declaration of which CNR command keys carry sensitive data
 * (account password, domain authorization code). {@see SocketConfig} and
 * {@see Response} both read {@see KEYS} for their `$sensitiveFields` default
 * instead of each hard-coding the same literal array, so the two lists can no
 * longer silently diverge.
 *
 * Not part of the SDK's public surface — an internal anti-drift holder, not a
 * capability a consumer of the CNR API has any use for.
 *
 * @internal
 * @package CNIC\CNR
 */
final class SensitiveFields
{
    /**
     * CNR carries sensitive data under upper-case command keys.
     * @var string[]
     */
    public const array KEYS = ["PASSWORD", "AUTH"];
}
