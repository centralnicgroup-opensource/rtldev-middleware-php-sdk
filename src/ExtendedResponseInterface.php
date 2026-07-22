<?php

declare(strict_types=1);

/**
 * CNIC
 * Copyright © Team Internet Group PLC
 */

namespace CNIC;

/**
 * Extended Response Interface
 *
 * The optional capabilities of a richer API surface (currently CentralNic
 * Reseller): server-side telemetry (queuetime/runtime), transient/pending
 * status signals and the table-friendly list-hash projection. These are NOT
 * part of the universal {@see ResponseInterface} because flat APIs such as
 * IBS/Moniker do not provide them — their responses implement the core
 * interface only. Consumers holding the shared type narrow to this one via
 * `instanceof ExtendedResponseInterface` before using any of these methods.
 *
 * @psalm-api
 * @package CNIC
 */
interface ExtendedResponseInterface extends ResponseInterface
{
    /**
     * Get Queuetime of API response
     * @return float Queuetime of API response
     */
    public function getQueuetime(): float;

    /**
     * Get Runtime of API response
     * @return float Runtime of API response
     */
    public function getRuntime(): float;

    /**
     * Check if current API response represents a temporary error case
     * API response code is an 4xx code
     * @return bool result
     */
    public function isTmpError(): bool;

    /**
     * Check if current operation is returned as pending
     * @return bool result
     */
    public function isPending(): bool;

    /**
     * Get Response as List Hash including useful meta data for tables
     * @return array<mixed> hash including list meta data and array of rows in hash notation
     */
    public function getListHash(): array;
}
