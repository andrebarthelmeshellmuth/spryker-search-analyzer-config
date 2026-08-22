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
use Spryker\Zed\Kernel\Business\AbstractFacade;
use SprykerCommunity\Shared\SearchAnalyzerConfig\SearchAnalyzerConfigConfig;
use SprykerCommunity\Shared\SearchAnalyzerConfig\SearchIndexManagedScopeMatcher;
use SprykerCommunity\Zed\SearchAnalyzerConfig\Business\Exception\SearchAnalyzerConfigInvalidTermException;

/**
 * @method \SprykerCommunity\Zed\SearchAnalyzerConfig\Business\SearchAnalyzerConfigBusinessFactory getFactory()
 * @method \SprykerCommunity\Zed\SearchAnalyzerConfig\Persistence\SearchAnalyzerConfigRepositoryInterface getRepository()
 * @method \SprykerCommunity\Zed\SearchAnalyzerConfig\Persistence\SearchAnalyzerConfigEntityManagerInterface getEntityManager()
 */
class SearchAnalyzerConfigFacade extends AbstractFacade implements SearchAnalyzerConfigFacadeInterface
{
    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @param string $sourceIdentifier
     * @param string $storeName
     */
    public function findByScope(string $sourceIdentifier, string $storeName): ?SearchAnalyzerConfigTransfer
    {
        return $this->getRepository()->findByScope($sourceIdentifier, $storeName);
    }

    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @param \Generated\Shared\Transfer\SearchAnalyzerConfigTransfer $searchAnalyzerConfigTransfer
     * @param string $changeSource
     * @param string|null $triggeredByUser
     *
     * @return array<string>
     */
    public function save(
        SearchAnalyzerConfigTransfer $searchAnalyzerConfigTransfer,
        string $changeSource,
        ?string $triggeredByUser,
    ): array {
        $errors = $this->getFactory()->createSearchAnalyzerConfigValidator()->validate($searchAnalyzerConfigTransfer);

        if ($errors !== []) {
            return $errors;
        }

        $errors = $this->getFactory()
            ->createSearchAnalyzerConfigPreviewer()
            ->validateAgainstLiveCluster(
                $searchAnalyzerConfigTransfer->getSourceIdentifierOrFail(),
                $searchAnalyzerConfigTransfer->getStoreNameOrFail(),
                $searchAnalyzerConfigTransfer,
            );

        if ($errors !== []) {
            return $errors;
        }

        $this->getEntityManager()->saveSearchAnalyzerConfig($searchAnalyzerConfigTransfer, $changeSource, $triggeredByUser);

        return [];
    }

    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @param string $sourceIdentifier
     * @param string $targetSourceIdentifier
     * @param string $sourceStoreName
     * @param string $targetStoreName
     * @param string|null $triggeredByUser
     *
     * @return array<string>
     */
    public function copy(
        string $sourceIdentifier,
        string $targetSourceIdentifier,
        string $sourceStoreName,
        string $targetStoreName,
        ?string $triggeredByUser,
    ): array {
        $sourceSearchAnalyzerConfigTransfer = $this->getRepository()->findByScope($sourceIdentifier, $sourceStoreName);

        if ($sourceSearchAnalyzerConfigTransfer === null) {
            return [sprintf('No config staged for source "%s" / store "%s" -- nothing to copy.', $sourceIdentifier, $sourceStoreName)];
        }

        $copySearchAnalyzerConfigTransfer = $this->getFactory()
            ->createSearchAnalyzerConfigCopier()
            ->copy($sourceSearchAnalyzerConfigTransfer, $targetSourceIdentifier, $targetStoreName);

        return $this->save($copySearchAnalyzerConfigTransfer, SearchAnalyzerConfigConfig::CHANGE_SOURCE_COPY, $triggeredByUser);
    }

    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @param string $sourceIdentifier
     * @param string $storeName
     * @param array<string, mixed> $baseSettings
     *
     * @return array<string, mixed>
     */
    public function renderIntoSettings(string $sourceIdentifier, string $storeName, array $baseSettings): array
    {
        $searchAnalyzerConfigTransfer = $this->getRepository()->findByScope($sourceIdentifier, $storeName);

        if ($searchAnalyzerConfigTransfer === null) {
            return $baseSettings;
        }

        try {
            return $this->getFactory()
                ->createSearchAnalyzerConfigRenderer()
                ->render($searchAnalyzerConfigTransfer, $baseSettings);
        } catch (SearchAnalyzerConfigInvalidTermException) {
            // Called from the real async search-index-alias rebuild worker -- a scope whose staged config
            // no longer renders (e.g. an invalid do-not-decompound term slipped past save-time validation)
            // must not crash the whole rebuild. Save-time validation is the primary safety net; this is
            // the fallback for a config that went stale after it was already saved.
            return $baseSettings;
        }
    }

    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @param string $sourceIdentifier
     * @param string $storeName
     * @param int $revision
     * @param string $appliedIndexName
     */
    public function markApplied(string $sourceIdentifier, string $storeName, int $revision, string $appliedIndexName): void
    {
        $this->getEntityManager()->markApplied($sourceIdentifier, $storeName, $revision, $appliedIndexName);
    }

    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @param string $sourceIdentifier
     * @param string $storeName
     *
     * @return array<\Generated\Shared\Transfer\SearchAnalyzerConfigRevisionTransfer>
     */
    public function getRevisionHistory(string $sourceIdentifier, string $storeName): array
    {
        return $this->getRepository()->getRevisionHistory($sourceIdentifier, $storeName);
    }

    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @param string $sourceIdentifier
     * @param string $storeName
     * @param int $revision
     * @param string|null $triggeredByUser
     *
     * @return array<string>
     */
    public function restoreRevision(string $sourceIdentifier, string $storeName, int $revision, ?string $triggeredByUser): array
    {
        $searchAnalyzerConfigTransfer = $this->findRevisionSnapshot($sourceIdentifier, $storeName, $revision);

        if ($searchAnalyzerConfigTransfer === null) {
            return [sprintf('Revision %d not found for this scope.', $revision)];
        }

        return $this->save($searchAnalyzerConfigTransfer, SearchAnalyzerConfigConfig::CHANGE_SOURCE_RESTORE, $triggeredByUser);
    }

    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @param string $sourceIdentifier
     * @param string $storeName
     * @param string $targetAnalyzerName
     * @param string $inputText
     */
    public function preview(
        string $sourceIdentifier,
        string $storeName,
        string $targetAnalyzerName,
        string $inputText,
    ): SearchAnalyzerConfigPreviewResultTransfer {
        return $this->getFactory()
            ->createSearchAnalyzerConfigPreviewer()
            ->preview($sourceIdentifier, $storeName, $targetAnalyzerName, $inputText);
    }

    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @return array<\Generated\Shared\Transfer\SearchIndexScopeTransfer>
     */
    public function getManagedScopes(): array
    {
        return $this->getFactory()->getSearchIndexAliasFacade()->getManagedScopes();
    }

    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @param string $sourceIdentifier
     * @param string $storeName
     * @param string|null $triggeredByUser
     */
    public function requestRebuild(string $sourceIdentifier, string $storeName, ?string $triggeredByUser): ?SearchIndexRolloutTransfer
    {
        $searchIndexScopeTransfer = SearchIndexManagedScopeMatcher::match($this->getManagedScopes(), $sourceIdentifier, $storeName);

        if ($searchIndexScopeTransfer === null) {
            return null;
        }

        $searchIndexRolloutTransfer = $this->getFactory()->getSearchIndexAliasFacade()->requestRebuildAsync($searchIndexScopeTransfer, $triggeredByUser);

        $searchAnalyzerConfigTransfer = $this->getRepository()->findByScope($sourceIdentifier, $storeName);
        $targetIndexName = $searchIndexRolloutTransfer->getTargetIndexName();

        // A rollout that failed before a target index was even created (e.g. "scope has no live index
        // yet -- use adoption, not a rebuild, for first-time setup", see RolloutStarter's own doc block)
        // has no target index to mark applied against -- its own failure reason, carried on
        // $searchIndexRolloutTransfer, is what the caller (the Zed Apply button) should surface instead.
        if ($searchAnalyzerConfigTransfer !== null && $targetIndexName !== null) {
            // Records "a rebuild was requested for this revision", not "the rollout finished/flipped" --
            // search-index-alias exposes no rollout-completion hook this package could listen to instead.
            $this->getEntityManager()->markApplied(
                $sourceIdentifier,
                $storeName,
                $searchAnalyzerConfigTransfer->getRevisionOrFail(),
                $targetIndexName,
            );
        }

        return $searchIndexRolloutTransfer;
    }

    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @return array<string>
     */
    public function pruneOrphanedPreviewIndices(): array
    {
        return $this->getFactory()->createSearchAnalyzerConfigPreviewer()->pruneOrphanedPreviewIndices();
    }

    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @param \Generated\Shared\Transfer\SearchAnalyzerConfigTransfer $searchAnalyzerConfigTransfer
     *
     * @return array<string>
     */
    public function collectMissingSlotWarnings(SearchAnalyzerConfigTransfer $searchAnalyzerConfigTransfer): array
    {
        return $this->getFactory()
            ->createSearchAnalyzerConfigPreviewer()
            ->collectMissingSlotWarnings(
                $searchAnalyzerConfigTransfer->getSourceIdentifierOrFail(),
                $searchAnalyzerConfigTransfer->getStoreNameOrFail(),
                $searchAnalyzerConfigTransfer,
            );
    }

    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @param string $sourceIdentifier
     * @param string $storeName
     *
     * @return array<string, array<string, bool>>
     */
    public function describeSlotAvailability(string $sourceIdentifier, string $storeName): array
    {
        return $this->getFactory()
            ->createSearchAnalyzerConfigPreviewer()
            ->describeSlotAvailability($sourceIdentifier, $storeName);
    }

    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @param string $sourceIdentifier
     * @param string $storeName
     *
     * @return array<string>
     */
    public function getManagedAnalyzerNames(string $sourceIdentifier, string $storeName): array
    {
        return $this->getFactory()
            ->createSearchAnalyzerConfigPreviewer()
            ->getManagedAnalyzerNames($sourceIdentifier, $storeName);
    }

    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @param string $sourceIdentifier
     * @param string $storeName
     */
    public function findAppliedSnapshot(string $sourceIdentifier, string $storeName): ?SearchAnalyzerConfigTransfer
    {
        $appliedRevision = $this->getRepository()->findByScope($sourceIdentifier, $storeName)?->getAppliedRevision();

        if ($appliedRevision === null) {
            return null;
        }

        return $this->findRevisionSnapshot($sourceIdentifier, $storeName, $appliedRevision);
    }

    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @param string $sourceIdentifier
     * @param string $storeName
     * @param \Generated\Shared\Transfer\SearchAnalyzerConfigTransfer $searchAnalyzerConfigTransfer
     */
    public function describeEditedSlots(
        string $sourceIdentifier,
        string $storeName,
        SearchAnalyzerConfigTransfer $searchAnalyzerConfigTransfer,
    ): array {
        return $this->getFactory()
            ->createSearchAnalyzerConfigPreviewer()
            ->describeEditedSlots($sourceIdentifier, $storeName, $searchAnalyzerConfigTransfer);
    }

    /**
     * @param string $sourceIdentifier
     * @param string $storeName
     * @param int $revision
     */
    protected function findRevisionSnapshot(string $sourceIdentifier, string $storeName, int $revision): ?SearchAnalyzerConfigTransfer
    {
        foreach ($this->getRepository()->getRevisionHistory($sourceIdentifier, $storeName) as $searchAnalyzerConfigRevisionTransfer) {
            if ($searchAnalyzerConfigRevisionTransfer->getRevision() === $revision) {
                return $searchAnalyzerConfigRevisionTransfer->getSearchAnalyzerConfig();
            }
        }

        return null;
    }
}
