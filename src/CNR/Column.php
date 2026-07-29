<?php

declare(strict_types=1);

/**
 * CNIC\CNR
 * Copyright © Team Internet Group PLC
 */

namespace CNIC\CNR;

use CNIC\Column as BaseColumn;

/**
 * CNR Column
 *
 * CNR responses are plaintext, so every column value is a string. This subclass
 * exists purely to say so: it binds the shared column's TValue to `string` and
 * narrows `getDataByIndex()` from `mixed` to `?string` for callers holding a
 * CNR-typed column. All behaviour is inherited — see {@see BaseColumn}.
 *
 * @extends BaseColumn<string>
 * @psalm-api
 * @package CNIC\CNR
 */
class Column extends BaseColumn
{
    /**
     * Get column data at given index
     * @param integer $idx data index
     */
    #[\Override]
    public function getDataByIndex(int $idx): string|null
    {
        return parent::getDataByIndex($idx);
    }
}
