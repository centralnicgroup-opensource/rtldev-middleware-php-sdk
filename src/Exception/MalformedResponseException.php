<?php

declare(strict_types=1);

/**
 * CNIC\Exception
 * Copyright © Team Internet Group PLC
 */

namespace CNIC\Exception;

/**
 * Thrown when the API sent a shape the SDK cannot represent.
 *
 * Raised by {@see \CNIC\CNR\Response::stringCells()} when a `PROPERTY` entry is
 * not a list, or one of its cells is not a string. This is deliberately NOT the
 * "capability absent on this platform or response" meaning
 * {@see UnsupportedFeatureException} documents on its own class — nothing is
 * missing here, the wire sent something the SDK's data model has no way to
 * hold.
 *
 * It extends {@see UnsupportedFeatureException} rather than {@see CnicException}
 * **deliberately**: both `stringCells()` throw sites already raised
 * `UnsupportedFeatureException` before this type existed, so re-parenting them
 * would break an existing `catch (UnsupportedFeatureException)` at either site.
 * Extending instead of replacing keeps the split purely additive. Closes the
 * open item recorded under RSRMID-2924 in docs/agents/architecture.md.
 *
 * @psalm-api
 * @package CNIC\Exception
 */
class MalformedResponseException extends UnsupportedFeatureException
{
}
