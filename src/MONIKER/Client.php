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
     * Narrowed one step further than {@see IBSClient::__construct()}: this brand
     * shares the IBS platform but not its endpoints, so accepting an
     * `IBS\SocketConfig` here would let a Moniker client silently talk to the IBS
     * host. Refusing it at the call site is the whole reason the parameter is
     * narrowed per brand rather than typed `?AbstractSocketConfig` once
     * (RSRMID-2966).
     *
     * @param SocketConfig|null $socketConfig Moniker connection configuration to
     *        adopt; null builds the brand default
     */
    public function __construct(?SocketConfig $socketConfig = null)
    {
        parent::__construct($socketConfig);
    }

    /**
     * Instantiate MONIKER SocketConfig
     */
    #[\Override]
    protected function newSocketConfig(): SocketConfig
    {
        return new SocketConfig();
    }
}
