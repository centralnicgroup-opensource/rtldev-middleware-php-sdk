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
