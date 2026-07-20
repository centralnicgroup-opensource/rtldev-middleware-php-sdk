<?php

declare(strict_types=1);

/**
 * CNIC\Exception
 * Copyright © Team Internet Group PLC
 */

namespace CNIC\Exception;

/**
 * Base class for every exception the CNIC SDK throws.
 *
 * All SDK-specific exceptions extend this base, which in turn extends the SPL
 * {@see \Exception}. Consumers can therefore catch any SDK failure in one place
 * with `catch (\CNIC\Exception\CnicException $e)` while pre-existing
 * `catch (\Exception $e)` code keeps working unchanged — the hierarchy is
 * purely additive and non-breaking.
 *
 * @psalm-api
 * @package CNIC\Exception
 */
class CnicException extends \Exception
{
}
