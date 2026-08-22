<?php

/**
 * This file is part of the spryker-community/search-analyzer-config package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchAnalyzerConfig\Persistence;

use Generated\Shared\Transfer\SearchAnalyzerConfigTransfer;

interface SearchAnalyzerConfigEntityManagerInterface
{
    /**
     * Full-replace save: the parent row's editable columns are overwritten, and every term row for this
     * scope is deleted and re-created from $searchAnalyzerConfigTransfer's own term lists -- there is no
     * per-term diffing, the whole list is the unit of change (matches the GUI grid's own "edit the whole
     * list, then Save" interaction model). Bumps revision by 1 and writes one new
     * spy_search_analyzer_config_revision snapshot row.
     *
     * @param \Generated\Shared\Transfer\SearchAnalyzerConfigTransfer $searchAnalyzerConfigTransfer
     * @param string $changeSource SearchAnalyzerConfigConfig::CHANGE_SOURCE_*.
     * @param string|null $triggeredByUser
     */
    public function saveSearchAnalyzerConfig(
        SearchAnalyzerConfigTransfer $searchAnalyzerConfigTransfer,
        string $changeSource,
        ?string $triggeredByUser,
    ): SearchAnalyzerConfigTransfer;

    /**
     * @param string $sourceIdentifier
     * @param string $storeName
     * @param int $revision
     * @param string $appliedIndexName
     */
    public function markApplied(
        string $sourceIdentifier,
        string $storeName,
        int $revision,
        string $appliedIndexName,
    ): void;
}
