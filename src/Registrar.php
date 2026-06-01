<?php

declare(strict_types=1);

/**
 * CNIC
 * Copyright © CentralNic Group PLC
 */

namespace CNIC;

/**
 * Supported and legacy registrar identifiers.
 *
 * @psalm-api
 * @package CNIC
 */
enum Registrar: string
{
    case CNR     = "CNR";
    case CNIC    = "CNIC";    // legacy alias for CNR
    case IBS     = "IBS";
    case MONIKER = "MONIKER";
    case HEXONET = "HEXONET"; // EOL — use version 11 of this library
    case ISPAPI  = "ISPAPI";  // EOL — use version 11 of this library
}
