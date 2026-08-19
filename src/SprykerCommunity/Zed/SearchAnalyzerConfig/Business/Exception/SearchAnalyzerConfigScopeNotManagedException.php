<?php

/**
 * This file is part of the spryker-community/search-analyzer-config package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchAnalyzerConfig\Business\Exception;

use RuntimeException;

/**
 * Thrown by SearchAnalyzerConfigPreviewer when the given (sourceIdentifier, storeName) is not among
 * search-index-alias's own managed scopes -- there is then no live alias to read a base analyzer chain
 * from, and nothing a preview could meaningfully diff against.
 */
class SearchAnalyzerConfigScopeNotManagedException extends RuntimeException
{
}
