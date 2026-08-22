<?php

/**
 * This file is part of the spryker-community/search-analyzer-config package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Shared\SearchAnalyzerConfig;

use Generated\Shared\Transfer\SearchIndexScopeTransfer;

/**
 * The single "is source/store X in this list of managed scopes" lookup shared by the Facade, the
 * Previewer, and multiple Communication controllers. Each caller owns its own miss behavior (return null
 * vs. throw); only the match loop itself lives here.
 */
class SearchIndexManagedScopeMatcher
{
    /**
     * @param array<\Generated\Shared\Transfer\SearchIndexScopeTransfer> $managedScopes
     * @param string $sourceIdentifier
     * @param string $storeName
     */
    public static function match(array $managedScopes, string $sourceIdentifier, string $storeName): ?SearchIndexScopeTransfer
    {
        foreach ($managedScopes as $searchIndexScopeTransfer) {
            if ($searchIndexScopeTransfer->getSourceIdentifier() === $sourceIdentifier && $searchIndexScopeTransfer->getStoreName() === $storeName) {
                return $searchIndexScopeTransfer;
            }
        }

        return null;
    }
}
