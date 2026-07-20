<?php

declare(strict_types=1);

/**
 * CNIC\Exception
 * Copyright © Team Internet Group PLC
 */

namespace CNIC\Exception;

/**
 * Thrown when a registrar identifier cannot be resolved to a Client subtype.
 *
 * Raised by {@see \CNIC\ClientFactory::getClient()} when the given registrar
 * string does not match any supported {@see \CNIC\Registrar} value.
 *
 * @psalm-api
 * @package CNIC\Exception
 */
class UnknownRegistrarException extends CnicException
{
}
