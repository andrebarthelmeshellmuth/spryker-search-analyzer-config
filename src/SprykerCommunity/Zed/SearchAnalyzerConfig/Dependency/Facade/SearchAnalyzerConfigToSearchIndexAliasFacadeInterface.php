<?php

/**
 * This file is part of the spryker-community/search-analyzer-config package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchAnalyzerConfig\Dependency\Facade;

use Generated\Shared\Transfer\SearchIndexRolloutTransfer;
use Generated\Shared\Transfer\SearchIndexScopeTransfer;

/**
 * The only place this package reaches across into its sibling `spryker-community/search-index-alias`
 * (a real, non-optional composer dependency -- this package has nothing to materialize a staged config
 * into without it). Scoped to exactly the two things a GUI/console needs beyond the rebuild-target plugin
 * itself: enumerating scopes for the picker, and triggering a rebuild for the explicit "Apply" action
 * (see the plan's locked-in "Apply UX always two-step" decision -- Save only stages, a SEPARATE action
 * triggers the rebuild).
 */
interface SearchAnalyzerConfigToSearchIndexAliasFacadeInterface
{
    /**
     * @return array<\Generated\Shared\Transfer\SearchIndexScopeTransfer>
     */
    public function getManagedScopes(): array;

    /**
     * @param \Generated\Shared\Transfer\SearchIndexScopeTransfer $searchIndexScopeTransfer
     * @param string|null $triggeredByUser
     * @param array<string, mixed>|null $targetMappingProperties
     * @param bool $optimizeForBulkLoad
     */
    public function requestRebuildAsync(
        SearchIndexScopeTransfer $searchIndexScopeTransfer,
        ?string $triggeredByUser = null,
        ?array $targetMappingProperties = null,
        bool $optimizeForBulkLoad = false,
    ): SearchIndexRolloutTransfer;
}
