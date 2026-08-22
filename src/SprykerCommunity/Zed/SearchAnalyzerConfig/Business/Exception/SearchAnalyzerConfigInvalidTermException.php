<?php

/**
 * This file is part of the spryker-community/search-analyzer-config package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchAnalyzerConfig\Business\Exception;

use RuntimeException;

/**
 * Thrown by SearchAnalyzerConfigRenderer as a defensive, belt-and-suspenders re-check immediately before
 * a do-not-decompound term is inlined into Painless script source (see SearchAnalyzerConfigValidator's
 * own doc block for why that inlining is a security boundary). Should be unreachable in normal operation
 * -- the GUI form and SearchAnalyzerConfigValidator already reject an invalid term before it can be
 * persisted -- this exists only to fail loudly instead of silently if that guarantee is ever broken
 * upstream (e.g. a direct DB write, a future import path).
 */
class SearchAnalyzerConfigInvalidTermException extends RuntimeException
{
}
