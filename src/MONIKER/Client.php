<?php

declare(strict_types=1);

/**
 * CNIC\MONIKER
 * Copyright © CentralNic Group PLC
 */

namespace CNIC\MONIKER;

use CNIC\IBS\Client as IBSClient;

/**
 * Moniker API Client — same platform as IBS; config.json provides Moniker-specific endpoints.
 *
 * @package CNIC\MONIKER
 */
class Client extends IBSClient
{
}
