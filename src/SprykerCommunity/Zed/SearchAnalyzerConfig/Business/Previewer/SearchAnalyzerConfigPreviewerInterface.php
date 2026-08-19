<?php

/**
 * This file is part of the spryker-community/search-analyzer-config package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchAnalyzerConfig\Business\Previewer;

use Generated\Shared\Transfer\SearchAnalyzerConfigPreviewResultTransfer;
use Generated\Shared\Transfer\SearchAnalyzerConfigTransfer;

interface SearchAnalyzerConfigPreviewerInterface
{
    /**
     * Runs $inputText through $targetAnalyzerName twice: once as it is LIVE today, once as it would be
     * with this scope's currently-staged (not-yet-applied) config rendered in -- via a throwaway index,
     * created and deleted within this one call. See the class docblock for why a throwaway index is
     * unavoidable here.
     *
     * @param string $sourceIdentifier
     * @param string $storeName
     * @param string $targetAnalyzerName One of SearchAnalyzerConfigConfig::getTargetAnalyzerNames().
     * @param string $inputText
     *
     * @throws \SprykerCommunity\Zed\SearchAnalyzerConfig\Business\Exception\SearchAnalyzerConfigScopeNotManagedException
     */
    public function preview(
        string $sourceIdentifier,
        string $storeName,
        string $targetAnalyzerName,
        string $inputText,
    ): SearchAnalyzerConfigPreviewResultTransfer;

    /**
     * Deletes any throwaway preview/probe index older than the configured age threshold -- a safety net
     * for a request that crashed or timed out before its own `finally` cleanup ran. See
     * `search-analyzer-config:prune-preview-indices`.
     *
     * @return array<string> Names of the indices that were deleted.
     */
    public function pruneOrphanedPreviewIndices(): array;

    /**
     * Two layers: first a pure structural check (does every field with an active value have its
     * well-known filter slot referenced by the target analyzer's own chain -- see
     * SearchAnalyzerConfigRenderer's own class doc block for why this package never adds that slot
     * itself), then, only if that passes and something would actually change, a real create-and-delete
     * probe against the live cluster asking OpenSearch itself whether the resulting settings are legal
     * (e.g. a stemmer language OpenSearch doesn't recognize). No-op (returns no errors) when the scope
     * isn't (yet) a search-index-alias managed scope, since there's nothing live to validate against and
     * nothing that could be applied either way.
     *
     * @param string $sourceIdentifier
     * @param string $storeName
     * @param \Generated\Shared\Transfer\SearchAnalyzerConfigTransfer $searchAnalyzerConfigTransfer
     *
     * @return array<string> Validation error messages; empty means compatible (or not checkable).
     */
    public function validateAgainstLiveCluster(
        string $sourceIdentifier,
        string $storeName,
        SearchAnalyzerConfigTransfer $searchAnalyzerConfigTransfer,
    ): array;
}
