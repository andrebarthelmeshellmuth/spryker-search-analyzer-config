<?php

/**
 * This file is part of the spryker-community/search-analyzer-config package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchAnalyzerConfig\Dependency\Facade;

use Generated\Shared\Transfer\SearchIndexRolloutTransfer;
use Generated\Shared\Transfer\SearchIndexScopeTransfer;

class SearchAnalyzerConfigToSearchIndexAliasFacadeBridge implements SearchAnalyzerConfigToSearchIndexAliasFacadeInterface
{
    /**
     * @var \SprykerCommunity\Zed\SearchIndexAlias\Business\SearchIndexAliasFacadeInterface
     */
    protected $searchIndexAliasFacade;

    /**
     * @param \SprykerCommunity\Zed\SearchIndexAlias\Business\SearchIndexAliasFacadeInterface $searchIndexAliasFacade
     */
    public function __construct($searchIndexAliasFacade)
    {
        $this->searchIndexAliasFacade = $searchIndexAliasFacade;
    }

    /**
     * @return array<\Generated\Shared\Transfer\SearchIndexScopeTransfer>
     */
    public function getManagedScopes(): array
    {
        return $this->searchIndexAliasFacade->getManagedScopes();
    }

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
    ): SearchIndexRolloutTransfer {
        return $this->searchIndexAliasFacade->requestRebuildAsync(
            $searchIndexScopeTransfer,
            $triggeredByUser,
            $targetMappingProperties,
            $optimizeForBulkLoad,
        );
    }
}
