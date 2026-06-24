<?php

declare(strict_types=1);

/**
 * CNIC\CNR
 * Copyright © Team Internet Group PLC
 */

namespace CNIC\CNR;

/**
 * CNR Session API Client
 *
 * @psalm-api
 * @package CNIC\CNR
 */
class SessionClient extends Client
{
    use SessionCapable;
}
