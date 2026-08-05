<?php

declare(strict_types=1);

/**
 * CNIC\Exception
 * Copyright © Team Internet Group PLC
 */

namespace CNIC\Exception;

/**
 * Thrown when a column name is registered twice on one response.
 *
 * Raised by {@see \CNIC\AbstractResponse::registerColumn()}. A response's
 * column list is keyed by name — `getColumn()` resolves a name to one position
 * — so a second column under the same name cannot be represented: the list
 * would hold a column `getColumn()` can never return (RSRMID-2939).
 *
 * Unreachable from either shipped brand, and from a substitute
 * {@see \CNIC\ResponseParserInterface} too: both brands derive their column names
 * from `array_keys()` of the parsed hash, and two distinct PHP array keys cannot
 * stringify to the same name. It exists for a future brand whose `populate()`
 * builds its columns some other way — there a repeated name is a programming
 * error and says so rather than half-registering the column, the same policy as
 * {@see UnsupportedFeatureException} for a non-string CNR cell.
 *
 * @psalm-api
 * @package CNIC\Exception
 */
class DuplicateColumnException extends CnicException
{
}
