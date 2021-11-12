<?php

/**
 * CNIC
 * Copyright © CentralNic Group PLC
 */

namespace CNIC;

/**
 * Common API Object Interface
 *
 * @package CNIC
 */

interface ApiObjectInterface
{
    /**
     * Constructor
     * @param \CNIC\HEXONET\Client $cf registrar's client instance
     */
    public function __construct($cf);

    /**
     * Set the related Object ID
     * @param string $objectid object id
     * @return $this
     */
    public function setId(string $objectid): ApiObjectInterface;

    /**
     * Set the Object Class
     * @param string $objectclass object class
     * @return $this
     */
    public function setClass(string $objectclass): ApiObjectInterface;

    /**
     * Load Status Data
     * @param bool $refresh trigger fresh data load, by default false
     * @return $this
     */
    public function loadStatus(bool $refresh = false): ApiObjectInterface;

    /**
     * IDN Conversion
     * @return array
     */
    public function convert(): array;

    /**
     * Bulk IDN Conversion
     * @param array $domains List of Domains
     * @return array
     */
    public function convertbulk(array $domains): array;
}
