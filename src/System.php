<?php

declare(strict_types=1);

/**
 * CNIC
 * Copyright © Team Internet Group PLC
 */

namespace CNIC;

/**
 * API system a client is connected to.
 *
 * OT&E is the test/sandbox environment; LIVE is production. A client is always
 * on exactly one of the two, so the state is modelled as this enum rather than a
 * boolean flag.
 *
 * @psalm-api
 * @package CNIC
 */
enum System: string
{
    case OTE  = "OTE";
    case LIVE = "LIVE";
}
