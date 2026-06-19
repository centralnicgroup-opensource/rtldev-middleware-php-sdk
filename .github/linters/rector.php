<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\SetList;
use Rector\ValueObject\PhpVersion;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/../../src',
        __DIR__ . '/../../tests',
    ])
    // Pinned to 8.3: ceiling is set by WHMCS compatibility (see RSRMID-2826).
    ->withPhpVersion(PhpVersion::PHP_83)
    ->withSets([
        SetList::CODE_QUALITY,
        SetList::DEAD_CODE,
        SetList::PHP_83,
    ]);
