<?php

/**
 * This file is part of the spryker-community/search-analyzer-config package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

/*
 * Minimal config for standalone transfer generation. Not a real project's config_default.php — just the
 * handful of keys the transfer generator's own class-resolver reads before anything ever tries to reach
 * a network. None of this package's own `@group Portable` tests construct a real Elasticsearch client or
 * touch a database.
 */

declare(strict_types = 1);

use Spryker\Shared\Kernel\KernelConstants;

$config[KernelConstants::PROJECT_NAMESPACES] = [];
$config[KernelConstants::CORE_NAMESPACES] = ['SprykerCommunity', 'Spryker'];
