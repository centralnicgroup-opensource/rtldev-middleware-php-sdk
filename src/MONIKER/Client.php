<?php

declare(strict_types=1);

/**
 * CNIC\MONIKER
 * Copyright © Team Internet Group PLC
 */

namespace CNIC\MONIKER;

use CNIC\IBS\Client as IBSClient;

/**
 * Moniker API Client — same platform as IBS; only the endpoints differ.
 *
 * Deliberately **not** `final`, even though nothing in the SDK extends it. Psalm's
 * `ClassMustBeFinal` only fires on leaf classes, so `CNR\Client` and
 * `IBS\Client` escape it purely because something inside this repo happens to
 * extend them. Sealing this one brand on that accident would make consumer
 * extensibility differ per brand for a reason no consumer could infer, so the
 * issue is suppressed rather than obeyed: the brand clients are extensible as a
 * set, or none of them are.
 *
 * @psalm-suppress ClassMustBeFinal brand clients stay extensible as a set — see above
 * @package CNIC\MONIKER
 */
class Client extends IBSClient
{
    /**
     * Instantiate MONIKER SocketConfig
     */
    #[\Override]
    protected function newSocketConfig(): SocketConfig
    {
        return new SocketConfig();
    }
}
