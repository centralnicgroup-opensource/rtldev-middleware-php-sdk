<?php

declare(strict_types=1);

/**
 * CNIC\MONIKER
 * Copyright © CentralNic Group PLC
 */

namespace CNIC\MONIKER;

use CNIC\IBS\SocketConfig as IBSSocketConfig;

/**
 * Moniker SocketConfig — same API platform as IBS; only the endpoints differ.
 *
 * @package CNIC\MONIKER
 */
final class SocketConfig extends IBSSocketConfig
{
    protected string $oteUrl = "https://testapi.moniker.com/";
    protected string $liveUrl = "https://api.moniker.com/";
}
