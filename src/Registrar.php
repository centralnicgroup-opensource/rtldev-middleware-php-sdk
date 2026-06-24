<?php

declare(strict_types=1);

/**
 * CNIC
 * Copyright © Team Internet Group PLC
 */

namespace CNIC;

/**
 * Supported registrar identifiers.
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
}
