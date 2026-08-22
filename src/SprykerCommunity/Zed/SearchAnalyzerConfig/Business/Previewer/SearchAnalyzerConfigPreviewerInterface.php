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
     * @param string $targetAnalyzerName One of getManagedAnalyzerNames()'s result for this scope.
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
     * The remaining HARD gate before persisting: once something would actually change, a real
     * create-and-delete probe against the live cluster asking OpenSearch itself whether the resulting
     * settings are legal (e.g. a stemmer language OpenSearch doesn't recognize, or a chain-order mistake).
     * A field with an active value whose slot simply isn't referenced by ONE target analyzer is NOT an
     * error here -- see collectMissingSlotWarnings() for that, a non-fatal, pre-save warning the caller can
     * let the user confirm past. No-op (returns no errors) when
     * the scope isn't (yet) a search-index-alias managed scope, since there's nothing live to validate
     * against and nothing that could be applied either way.
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

    /**
     * Pre-save, non-fatal counterpart to validateAgainstLiveCluster() -- read-only (no probe index, no
     * persistence), cheap enough to call on every form submit before the user has confirmed anything.
     * Returns one message per (active field, target analyzer) combination where that analyzer's own chain
     * doesn't reference the field's well-known slot -- a deliberate per-analyzer opt-out is
     * indistinguishable from a forgotten schema declaration from here, so this is advisory only; the
     * caller decides whether to block on it or let the user confirm past it. Fails open (empty warnings)
     * for an unmanaged scope or a cluster hiccup, exactly like validateAgainstLiveCluster() does.
     *
     * @param string $sourceIdentifier
     * @param string $storeName
     * @param \Generated\Shared\Transfer\SearchAnalyzerConfigTransfer $searchAnalyzerConfigTransfer
     *
     * @return array<string>
     */
    public function collectMissingSlotWarnings(
        string $sourceIdentifier,
        string $storeName,
        SearchAnalyzerConfigTransfer $searchAnalyzerConfigTransfer,
    ): array;

    /**
     * Pure structural read for the Zed edit form to show UP FRONT, independent of any field's current
     * value -- unlike collectMissingSlotWarnings(), which only reports a slot missing for a field that's
     * currently active, this reports every chain-visible slot's presence on every target analyzer
     * regardless. Fails open (empty array) for an unmanaged scope or a cluster hiccup, exactly like
     * validateAgainstLiveCluster() does.
     *
     * @param string $sourceIdentifier
     * @param string $storeName
     *
     * @return array<string, array<string, bool>> Slot name => (analyzer name => referenced by that analyzer's own chain).
     */
    public function describeSlotAvailability(string $sourceIdentifier, string $storeName): array;

    /**
     * Analyzer names for the Zed edit/preview forms -- see
     * SearchAnalyzerConfigRendererInterface::resolveTargetAnalyzerNames() for the discovery rule. Fails
     * open (empty array) for an unmanaged scope or a cluster hiccup, exactly like the other live-cluster
     * reads on this class.
     *
     * @param string $sourceIdentifier
     * @param string $storeName
     *
     * @return array<string>
     */
    public function getManagedAnalyzerNames(string $sourceIdentifier, string $storeName): array;

    /**
     * Which of this package's well-known slots would actually change on the LIVE cluster if
     * $searchAnalyzerConfigTransfer were applied right now -- see the implementation's own doc block for
     * why this is diffed against real live filter bodies rather than `applied_revision`. Fails open
     * (every slot false) for an unmanaged scope or a cluster hiccup, exactly like the other live-cluster
     * reads on this class.
     *
     * @param string $sourceIdentifier
     * @param string $storeName
     * @param \Generated\Shared\Transfer\SearchAnalyzerConfigTransfer $searchAnalyzerConfigTransfer
     *
     * @return array<string, bool> Slot name => whether rendering $searchAnalyzerConfigTransfer would change that slot's live filter body.
     */
    public function describeEditedSlots(
        string $sourceIdentifier,
        string $storeName,
        SearchAnalyzerConfigTransfer $searchAnalyzerConfigTransfer,
    ): array;
}
