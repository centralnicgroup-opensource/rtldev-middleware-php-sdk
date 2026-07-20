<?php

declare(strict_types=1);

/**
 * CNIC\Exception
 * Copyright © Team Internet Group PLC
 */

namespace CNIC\Exception;

/**
 * Thrown when a capability is not available on the current platform or response.
 *
 * Some operations exist on the shared contract but are not offered by every
 * brand — e.g. the IBS/Moniker platform has no API session, user roles, high
 * performance connection setup, queue/runtime metrics, temporary-error or
 * pending states, and no server-side list-hash. Calling such a method raises
 * this exception instead of returning a misleading value.
 *
 * @psalm-api
 * @package CNIC\Exception
 */
class UnsupportedFeatureException extends CnicException
{
}
