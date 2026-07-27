<?php

declare(strict_types=1);

/**
 * CNIC\Exception
 * Copyright © Team Internet Group PLC
 */

namespace CNIC\Exception;

/**
 * Thrown when an API date/time value cannot be parsed.
 *
 * Raised by {@see \CNIC\ApiDateTime::from()} for any value that is not one of
 * the two shapes the Team Internet APIs emit — including values PHP's own date
 * handling would silently roll over into a different instant (`2026-02-30`
 * becoming `2026-03-02`, for example). The parser refuses those rather than
 * inventing a plausible-looking date. Use
 * {@see \CNIC\ApiDateTime::tryFrom()} when a `null` is preferable to an
 * exception.
 *
 * @psalm-api
 * @package CNIC\Exception
 */
class InvalidDateTimeException extends CnicException
{
}
