<?php

/**
 * This file is part of the spryker-community/search-analyzer-config package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchAnalyzerConfig\Business;

use Generated\Shared\Transfer\SearchAnalyzerConfigPreviewResultTransfer;
use Generated\Shared\Transfer\SearchAnalyzerConfigTransfer;
use Generated\Shared\Transfer\SearchIndexRolloutTransfer;

interface SearchAnalyzerConfigFacadeInterface
{
    /**
     * @api
     *
     * @param string $sourceIdentifier
     * @param string $storeName
     */
    public function findByScope(string $sourceIdentifier, string $storeName): ?SearchAnalyzerConfigTransfer;

    /**
     * Validates term-pattern safety first (see SearchAnalyzerConfigValidator), then probes the live
     * cluster to confirm the resulting settings are actually legal (see
     * SearchAnalyzerConfigPreviewer::validateAgainstLiveCluster()) -- an unrecognized value, or a
     * chain-order mistake OpenSearch itself rejects, fails loudly on Save, not silently until the next
     * Apply/rebuild. Does NOT block on a field with an active value whose well-known filter slot
     * (`sac_synonyms`, `sac_stemmer`, ...) simply isn't referenced by one of the project's target
     * analyzer(s) -- that's a deliberate per-analyzer opt-out, not an error (see
     * SearchAnalyzerConfigRenderer's own class doc block); call {@see collectMissingSlotWarnings()}
     * first if the caller wants to warn about that and let the user confirm past it.
     *
     * @api
     *
     * @param \Generated\Shared\Transfer\SearchAnalyzerConfigTransfer $searchAnalyzerConfigTransfer
     * @param string $changeSource SearchAnalyzerConfigConfig::CHANGE_SOURCE_*.
     * @param string|null $triggeredByUser
     *
     * @return array<string> Validation error messages; the config is only persisted when empty.
     */
    public function save(
        SearchAnalyzerConfigTransfer $searchAnalyzerConfigTransfer,
        string $changeSource,
        ?string $triggeredByUser,
    ): array;

    /**
     * Goes through the exact same validate-then-persist gate as {@see save()} -- the copied config is
     * validated against the TARGET scope's own project schema, not just duplicated blind, since a filter
     * slot the source analyzer had (and the target doesn't) would otherwise only surface later on Apply.
     *
     * @api
     *
     * @param string $sourceIdentifier
     * @param string $targetSourceIdentifier
     * @param string $sourceStoreName
     * @param string $targetStoreName
     * @param string|null $triggeredByUser
     *
     * @return array<string> Error messages (including "no config staged for the source scope"); the copy
     *   is only persisted when empty.
     */
    public function copy(
        string $sourceIdentifier,
        string $targetSourceIdentifier,
        string $sourceStoreName,
        string $targetStoreName,
        ?string $triggeredByUser,
    ): array;

    /**
     * Merges $sourceIdentifier/$storeName's staged config into $baseSettings -- the exact call
     * `SearchAnalyzerConfigTargetIndexSettingsExpanderPlugin` makes, exposed on the facade so it's also
     * usable from the preview/diff page (P6) against a throwaway index without going through a real
     * search-index-alias rebuild.
     *
     * @api
     *
     * @param string $sourceIdentifier
     * @param string $storeName
     * @param array<string, mixed> $baseSettings
     *
     * @return array<string, mixed>
     */
    public function renderIntoSettings(string $sourceIdentifier, string $storeName, array $baseSettings): array;

    /**
     * @api
     *
     * @param string $sourceIdentifier
     * @param string $storeName
     * @param int $revision
     * @param string $appliedIndexName
     */
    public function markApplied(string $sourceIdentifier, string $storeName, int $revision, string $appliedIndexName): void;

    /**
     * Newest first.
     *
     * @api
     *
     * @param string $sourceIdentifier
     * @param string $storeName
     *
     * @return array<\Generated\Shared\Transfer\SearchAnalyzerConfigRevisionTransfer>
     */
    public function getRevisionHistory(string $sourceIdentifier, string $storeName): array;

    /**
     * Re-saves an earlier revision's snapshot as a brand-new revision (change source "restore") -- an
     * append, never a rewind: the revision being restored FROM still exists in the history afterward.
     *
     * @api
     *
     * @param string $sourceIdentifier
     * @param string $storeName
     * @param int $revision
     * @param string|null $triggeredByUser
     *
     * @return array<string> Validation error messages; empty on success. Fails with a not-found message if $revision doesn't exist for this scope.
     */
    public function restoreRevision(string $sourceIdentifier, string $storeName, int $revision, ?string $triggeredByUser): array;

    /**
     * Runs $inputText through $targetAnalyzerName twice -- once as it is live today, once as it would be
     * with this scope's currently-staged config applied -- via a throwaway index. See
     * SearchAnalyzerConfigPreviewer's own doc block for why a throwaway index is unavoidable here.
     *
     * @api
     *
     * @param string $sourceIdentifier
     * @param string $storeName
     * @param string $targetAnalyzerName
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
     * Delegates to search-index-alias's own `getManagedScopes()` -- this package only ever edits config
     * for a scope search-index-alias actually manages, since there is nothing else it could materialize
     * an edit into.
     *
     * @api
     *
     * @return array<\Generated\Shared\Transfer\SearchIndexScopeTransfer>
     */
    public function getManagedScopes(): array;

    /**
     * The explicit "Apply" action (see [[search_analyzer_config_plan]]'s locked-in two-step Apply UX --
     * Save only stages, this is the SEPARATE action that triggers a real search-index-alias rebuild).
     * Delegates to `SearchIndexAliasFacadeInterface::requestRebuildAsync()`; the rebuild worker picks it
     * up from there, same as any other search-index-alias-triggered rebuild. The returned transfer can
     * carry a FAILED status (e.g. the scope has no live index yet and needs adoption, not a rebuild, for
     * first-time setup) -- that is NOT recorded as applied here (there is no target index to record), the
     * caller (the Zed Apply button) should check `getStatus()`/`getFailureReason()` and surface it.
     *
     * @api
     *
     * @param string $sourceIdentifier
     * @param string $storeName
     * @param string|null $triggeredByUser
     *
     * @return \Generated\Shared\Transfer\SearchIndexRolloutTransfer|null Null if $sourceIdentifier/$storeName is not a managed scope.
     */
    public function requestRebuild(string $sourceIdentifier, string $storeName, ?string $triggeredByUser): ?SearchIndexRolloutTransfer;

    /**
     * @api
     *
     * @return array<string> Names of the indices that were deleted.
     */
    public function pruneOrphanedPreviewIndices(): array;

    /**
     * Pre-save, non-fatal check for a caller (the Zed edit form) that wants to warn the user and let them
     * confirm past it, rather than have {@see save()} silently apply a field to fewer analyzers than they
     * might expect. Returns one message per (active field, target analyzer) combination where that
     * analyzer's own chain doesn't reference the field's well-known slot -- a deliberate per-analyzer
     * opt-out (see SearchAnalyzerConfigRenderer's own class doc block) is indistinguishable from a
     * forgotten schema declaration from here, so this is advisory only and never blocks {@see save()}
     * itself. Read-only: no cluster write, no persistence.
     *
     * @api
     *
     * @param \Generated\Shared\Transfer\SearchAnalyzerConfigTransfer $searchAnalyzerConfigTransfer
     *
     * @return array<string>
     */
    public function collectMissingSlotWarnings(SearchAnalyzerConfigTransfer $searchAnalyzerConfigTransfer): array;

    /**
     * Pure structural read for the Zed edit form to show UP FRONT, independent of any field's current
     * value -- unlike {@see collectMissingSlotWarnings()}, which only reports a slot missing for a field
     * that's currently active, this reports every chain-visible slot's presence (`sac_normalization`,
     * `sac_decompound`, `sac_synonyms`, `sac_stopwords`, `sac_keyword_marker`, `sac_stemmer`) on every
     * target analyzer regardless, before the user has typed anything. Read-only: no cluster write, no
     * persistence.
     *
     * @api
     *
     * @param string $sourceIdentifier
     * @param string $storeName
     *
     * @return array<string, array<string, bool>> Slot name => (analyzer name => referenced by that analyzer's own chain).
     */
    public function describeSlotAvailability(string $sourceIdentifier, string $storeName): array;

    /**
     * Analyzer names this package manages for this scope -- auto-discovered from the live cluster's
     * `analysis.analyzer` map, any analyzer whose own chain references at least one of this package's
     * well-known `sac_*` slot names. Read-only: no cluster write, no persistence. Fails open (empty array)
     * for an unmanaged scope, a cluster hiccup, or a store/locale with no live index yet.
     *
     * @api
     *
     * @param string $sourceIdentifier
     * @param string $storeName
     *
     * @return array<string>
     */
    public function getManagedAnalyzerNames(string $sourceIdentifier, string $storeName): array;

    /**
     * The full snapshot of whichever revision is currently LIVE for this scope (i.e. the revision named by
     * `SearchAnalyzerConfigTransfer::getAppliedRevision()`), for diffing against the currently staged
     * config -- see the Zed Edit page's per-filter "edited" badges. Null when nothing has ever been applied
     * for this scope yet (nothing live to diff against) or the scope doesn't exist.
     *
     * @api
     *
     * @param string $sourceIdentifier
     * @param string $storeName
     */
    public function findAppliedSnapshot(string $sourceIdentifier, string $storeName): ?SearchAnalyzerConfigTransfer;

    /**
     * Which of this package's well-known slots would actually change on the LIVE cluster if
     * $searchAnalyzerConfigTransfer were applied right now -- diffed against real live filter bodies, NOT
     * `applied_revision` (see SearchAnalyzerConfigPreviewer::describeEditedSlots()'s own doc block for
     * why). Always accurate, including for a scope that's never been through Apply. Fails open (every
     * slot false) for an unmanaged scope or a cluster hiccup.
     *
     * @api
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
