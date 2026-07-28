<?php

declare(strict_types=1);

/**
 * CNIC\Exception
 * Copyright © Team Internet Group PLC
 */

namespace CNIC\Exception;

/**
 * Thrown when a configuration value handed to the SDK is outside the range the
 * transport can act on.
 *
 * Distinct from {@see UnsupportedFeatureException}, which reports a capability
 * the platform does not offer: here the capability exists and the value is the
 * problem. Raised rather than passed through because cURL rejects such values
 * *quietly* — `curl_setopt()` returns `false` for a negative CURLOPT_TIMEOUT
 * and `curl_setopt_array()`'s return is not inspected, so the setting would be
 * dropped with no signal, which is the failure class RSRMID-2919 removed.
 *
 * @psalm-api
 * @package CNIC\Exception
 */
class InvalidConfigurationException extends CnicException
{
}
