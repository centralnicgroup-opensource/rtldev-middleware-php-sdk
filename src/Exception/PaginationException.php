<?php

declare(strict_types=1);

/**
 * CNIC\Exception
 * Copyright © Team Internet Group PLC
 */

namespace CNIC\Exception;

/**
 * Thrown when a list-pagination helper is used incorrectly.
 *
 * Raised by {@see \CNIC\CNR\Client::requestNextResponsePage()} when the current
 * command still carries a `LAST` parameter, which conflicts with the automatic
 * page-cursor arithmetic and must be removed before paginating.
 *
 * @psalm-api
 * @package CNIC\Exception
 */
class PaginationException extends CnicException
{
}
