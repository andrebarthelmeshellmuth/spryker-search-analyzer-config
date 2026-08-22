<?php

/**
 * This file is part of the spryker-community/search-analyzer-config package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchAnalyzerConfig\Business\Previewer;

use ArrayObject;
use Elastica\Client;
use Elastica\Exception\ExceptionInterface as ElasticaExceptionInterface;
use Elastica\Index;
use Generated\Shared\Transfer\SearchAnalyzerConfigPreviewResultTransfer;
use Generated\Shared\Transfer\SearchAnalyzerConfigPreviewStageTransfer;
use Generated\Shared\Transfer\SearchAnalyzerConfigTransfer;
use SprykerCommunity\Shared\SearchAnalyzerConfig\SearchIndexManagedScopeMatcher;
use SprykerCommunity\Zed\SearchAnalyzerConfig\Business\Client\ElasticaClientProviderInterface;
use SprykerCommunity\Zed\SearchAnalyzerConfig\Business\Exception\SearchAnalyzerConfigScopeNotManagedException;
use SprykerCommunity\Zed\SearchAnalyzerConfig\Business\Renderer\SearchAnalyzerConfigRenderer;
use SprykerCommunity\Zed\SearchAnalyzerConfig\Business\Renderer\SearchAnalyzerConfigRendererInterface;
use SprykerCommunity\Zed\SearchAnalyzerConfig\Dependency\Facade\SearchAnalyzerConfigToSearchIndexAliasFacadeInterface;
use SprykerCommunity\Zed\SearchAnalyzerConfig\Persistence\SearchAnalyzerConfigRepositoryInterface;
use Throwable;

/**
 * Answers "what would this staged, not-yet-applied config actually do to a real search string" -- see
 * [[search_analyzer_config_plan]]'s P6 and its own verified-live constraint: OpenSearch/Elasticsearch's
 * `_analyze` endpoint rejects nested inline filter objects in a `condition` filter's own `filter` array --
 * it only accepts references to NAMED filters already defined in a real index's `analysis` block. A
 * candidate settings fragment that exists only in this package's own DB therefore cannot be previewed
 * with a stateless `_analyze` call; it needs a REAL (if throwaway) index.
 *
 * The "before" side needs no throwaway index at all -- `_analyze` is read-only, so running it directly
 * against the scope's live alias is harmless and reflects exactly what search does today. Only "after"
 * (this package's own filters spliced in) needs the throwaway index, since that filter chain does not
 * exist on any real index yet.
 *
 * Stage mapping mirrors `spryker-community/search-debug`'s own `AnalysisStageMapper` (same `explain: true`
 * response shape: `charfilters`/`tokenizer`/`tokenfilters`, or a single `analyzer` fallback for a built-in,
 * non-custom analyzer) -- copied rather than shared per [[spryker_standalone_community_packages]], and
 * deliberately simplified to token strings only (no offsets/component-definition lookups), since this
 * page's job is a before/after diff, not a full analysis tree.
 */
class SearchAnalyzerConfigPreviewer implements SearchAnalyzerConfigPreviewerInterface
{
    /**
     * Shared by both `preview()`'s own throwaway "after" index and `validateAgainstLiveCluster()`'s
     * create-and-delete probe index -- same mechanism, same cleanup story, same prune command.
     *
     * @var string
     */
    protected const PREVIEW_INDEX_PREFIX = 'sac_preview_';

    /**
     * A preview index lives only for the duration of one request -- an hour old means its own `finally`
     * cleanup never ran (a crash, a timeout, a killed worker).
     *
     * @var int
     */
    protected const ORPHAN_AGE_SECONDS = 3600;

    /**
     * @param \SprykerCommunity\Zed\SearchAnalyzerConfig\Persistence\SearchAnalyzerConfigRepositoryInterface $repository
     * @param \SprykerCommunity\Zed\SearchAnalyzerConfig\Business\Renderer\SearchAnalyzerConfigRendererInterface $renderer
     * @param \SprykerCommunity\Zed\SearchAnalyzerConfig\Dependency\Facade\SearchAnalyzerConfigToSearchIndexAliasFacadeInterface $searchIndexAliasFacade
     * @param \SprykerCommunity\Zed\SearchAnalyzerConfig\Business\Client\ElasticaClientProviderInterface $elasticaClientProvider
     */
    public function __construct(
        protected SearchAnalyzerConfigRepositoryInterface $repository,
        protected SearchAnalyzerConfigRendererInterface $renderer,
        protected SearchAnalyzerConfigToSearchIndexAliasFacadeInterface $searchIndexAliasFacade,
        protected ElasticaClientProviderInterface $elasticaClientProvider,
    ) {
    }

    /**
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
        $aliasName = $this->resolveAliasName($sourceIdentifier, $storeName);
        $elasticaClient = $this->elasticaClientProvider->getClient();

        $beforeStages = $this->analyzeStages($elasticaClient->getIndex($aliasName), $targetAnalyzerName, $inputText);
        $afterStages = $this->buildAfterStages($elasticaClient, $aliasName, $sourceIdentifier, $storeName, $targetAnalyzerName, $inputText, $beforeStages);

        return (new SearchAnalyzerConfigPreviewResultTransfer())
            ->setInputText($inputText)
            ->setBeforeStages(new ArrayObject($beforeStages))
            ->setAfterStages(new ArrayObject($afterStages));
    }

    /**
     * @return array<string>
     */
    public function pruneOrphanedPreviewIndices(): array
    {
        $elasticaClient = $this->elasticaClientProvider->getClient();
        $deletedIndexNames = [];

        foreach ($this->findPreviewIndexNames($elasticaClient) as $previewIndexName) {
            $ageSeconds = $this->getIndexAgeSeconds($elasticaClient, $previewIndexName);

            if ($ageSeconds === null || $ageSeconds < static::ORPHAN_AGE_SECONDS) {
                continue;
            }

            $elasticaClient->getIndex($previewIndexName)->delete();
            $deletedIndexNames[] = $previewIndexName;
        }

        return $deletedIndexNames;
    }

    /**
     * The one remaining HARD gate before persisting: a real create-and-delete probe against the live
     * cluster (only reached once the render actually changes anything) -- catches anything a per-slot
     * check can't know about, e.g. a stemmer language value or synonym rule OpenSearch itself rejects, or
     * a chain-order mistake that makes index creation fail outright. A field with an active value whose
     * slot simply isn't referenced by one target analyzer is NOT checked here -- that's
     * collectMissingSlotWarnings()'s job, a non-fatal, pre-save warning the caller can let the user confirm
     * past.
     *
     * @param string $sourceIdentifier
     * @param string $storeName
     * @param \Generated\Shared\Transfer\SearchAnalyzerConfigTransfer $searchAnalyzerConfigTransfer
     *
     * @return array<string>
     */
    public function validateAgainstLiveCluster(
        string $sourceIdentifier,
        string $storeName,
        SearchAnalyzerConfigTransfer $searchAnalyzerConfigTransfer,
    ): array {
        try {
            $aliasName = $this->resolveAliasName($sourceIdentifier, $storeName);
        } catch (SearchAnalyzerConfigScopeNotManagedException) {
            // Nothing live to validate against, and nothing that could be applied either way -- see the
            // interface's own doc block.
            return [];
        }

        $elasticaClient = $this->elasticaClientProvider->getClient();

        try {
            $baseSettings = $this->readLiveAnalysisSettings($elasticaClient, $aliasName);
        } catch (ElasticaExceptionInterface) {
            // Fail OPEN, but only for a real cluster/connectivity/response problem: don't block an
            // otherwise-valid save over a cluster hiccup. If a problem is real, it still surfaces loudly
            // the next time Apply actually runs a rebuild (recorded in search-index-alias's own
            // spy_search_index_rollout.failure_reason) -- this check is a convenience that catches it
            // earlier, not the only safety net. A non-Elastica error (e.g. a bug in
            // selectNewestIndexSettings()) is NOT swallowed here -- it propagates, since silently
            // disabling validation over an internal bug would hide a real problem instead of failing open
            // on one.
            return [];
        }

        $afterSettings = $this->renderer->render($searchAnalyzerConfigTransfer, $baseSettings);

        if ($afterSettings === $baseSettings) {
            return [];
        }

        return $this->probeSettingsAgainstLiveCluster($elasticaClient, $aliasName, $afterSettings);
    }

    /**
     * Pre-save, non-fatal counterpart to validateAgainstLiveCluster()'s remaining hard checks -- read-only
     * (no probe index, no persistence), so it's cheap enough to call on every form submit before the user
     * has confirmed anything. Resolves the same live settings validateAgainstLiveCluster() would, then
     * defers the actual per-slot detection to SearchAnalyzerConfigRenderer::collectMissingSlotWarnings().
     * Fails open (empty warnings) for an unmanaged scope or a cluster hiccup, exactly like
     * validateAgainstLiveCluster() does -- a warning check has no business blocking a save over either.
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
    ): array {
        try {
            $aliasName = $this->resolveAliasName($sourceIdentifier, $storeName);
        } catch (SearchAnalyzerConfigScopeNotManagedException) {
            return [];
        }

        try {
            $baseSettings = $this->readLiveAnalysisSettings($this->elasticaClientProvider->getClient(), $aliasName);
        } catch (ElasticaExceptionInterface) {
            return [];
        }

        return $this->renderer->collectMissingSlotWarnings($searchAnalyzerConfigTransfer, $baseSettings);
    }

    /**
     * Pure structural read for the Zed edit form to show UP FRONT, independent of any field's current
     * value -- unlike collectMissingSlotWarnings(), which only reports a slot missing for a field that's
     * currently active. Fails open (empty array) for an unmanaged scope or a cluster hiccup, exactly like
     * the other two live-cluster reads on this class.
     *
     * @param string $sourceIdentifier
     * @param string $storeName
     *
     * @return array<string, array<string, bool>> Slot name => (analyzer name => referenced by that analyzer's own chain).
     */
    public function describeSlotAvailability(string $sourceIdentifier, string $storeName): array
    {
        try {
            $aliasName = $this->resolveAliasName($sourceIdentifier, $storeName);
        } catch (SearchAnalyzerConfigScopeNotManagedException) {
            return [];
        }

        try {
            $baseSettings = $this->readLiveAnalysisSettings($this->elasticaClientProvider->getClient(), $aliasName);
        } catch (ElasticaExceptionInterface) {
            return [];
        }

        return $this->renderer->describeSlotAvailability($baseSettings);
    }

    /**
     * Which of this package's well-known slots would actually change on the LIVE cluster if
     * $searchAnalyzerConfigTransfer were applied right now -- renders it against the real live settings
     * (same mechanism `validateAgainstLiveCluster()` uses) and diffs the resulting filter body per slot
     * against what's live today. Deliberately NOT based on `applied_revision`/revision history: this
     * package has no rollout-completion hook to reliably know when (or whether) a requested rebuild ever
     * finished, so a revision-based diff can go stale or never activate at all. Comparing actual rendered
     * filter bodies is always accurate and needs nothing to have been formally "applied" first -- a
     * scope that's never been through Apply still gets a correct diff against its real live analyzer.
     * Fails open (every slot false) for an unmanaged scope or a cluster hiccup, exactly like the other
     * live-cluster reads on this class.
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
    ): array {
        $editedSlots = array_fill_keys(SearchAnalyzerConfigRenderer::CHAIN_VISIBLE_SLOT_NAMES_IN_RECOMMENDED_ORDER, false);

        try {
            $aliasName = $this->resolveAliasName($sourceIdentifier, $storeName);
        } catch (SearchAnalyzerConfigScopeNotManagedException) {
            return $editedSlots;
        }

        try {
            $baseSettings = $this->readLiveAnalysisSettings($this->elasticaClientProvider->getClient(), $aliasName);
        } catch (ElasticaExceptionInterface) {
            return $editedSlots;
        }

        $afterSettings = $this->renderer->render($searchAnalyzerConfigTransfer, $baseSettings);

        foreach (array_keys($editedSlots) as $slotName) {
            $liveBody = $baseSettings['analysis']['filter'][$slotName] ?? null;
            $stagedBody = $afterSettings['analysis']['filter'][$slotName] ?? null;
            $editedSlots[$slotName] = $liveBody !== $stagedBody;
        }

        return $editedSlots;
    }

    /**
     * Analyzer names for the Zed edit/preview forms -- see SearchAnalyzerConfigRendererInterface::resolveTargetAnalyzerNames()
     * for the discovery rule. Fails open (empty array) for an unmanaged scope or a cluster hiccup, exactly
     * like the other live-cluster reads on this class.
     *
     * @param string $sourceIdentifier
     * @param string $storeName
     *
     * @return array<string>
     */
    public function getManagedAnalyzerNames(string $sourceIdentifier, string $storeName): array
    {
        try {
            $aliasName = $this->resolveAliasName($sourceIdentifier, $storeName);
        } catch (SearchAnalyzerConfigScopeNotManagedException) {
            return [];
        }

        try {
            $baseSettings = $this->readLiveAnalysisSettings($this->elasticaClientProvider->getClient(), $aliasName);
        } catch (ElasticaExceptionInterface) {
            return [];
        }

        return $this->renderer->resolveTargetAnalyzerNames($baseSettings);
    }

    /**
     * Creates a real (throwaway) index with the candidate settings and deletes it again -- the only way to
     * get a definitive, always-correct answer to "is this legal" is to ask OpenSearch itself, rather than
     * hand-maintaining a guessed list of everything that could go wrong. Same mechanism `preview()`
     * already uses for its own throwaway "after" index.
     *
     * @param \Elastica\Client $elasticaClient
     * @param string $aliasName
     * @param array<string, mixed> $candidateSettings
     *
     * @return array<string>
     */
    protected function probeSettingsAgainstLiveCluster(Client $elasticaClient, string $aliasName, array $candidateSettings): array
    {
        $probeIndex = $elasticaClient->getIndex($this->buildPreviewIndexName($aliasName));

        try {
            $probeIndex->create(['settings' => ['analysis' => $candidateSettings['analysis'] ?? []]]);
        } catch (Throwable $throwable) {
            $this->deleteIndexIfExistsQuietly($probeIndex);

            return [sprintf(
                'This config is not compatible with the project\'s configured target analyzer settings: %s',
                $throwable->getMessage(),
            )];
        }

        $this->deleteIndexIfExistsQuietly($probeIndex);

        return [];
    }

    /**
     * A cleanup failure here must never clobber a real result/error a caller already computed --
     * `pruneOrphanedPreviewIndices()` is the backstop for anything left behind by this failing.
     *
     * @param \Elastica\Index $index
     */
    protected function deleteIndexIfExistsQuietly(Index $index): void
    {
        try {
            if ($index->exists()) {
                $index->delete();
            }
        } catch (Throwable) {
            // Intentionally swallowed -- see method doc block.
        }
    }

    /**
     * No config staged for this scope means "after" is identical to "before" -- nothing to render, so no
     * throwaway index is needed at all.
     *
     * @param \Elastica\Client $elasticaClient
     * @param string $aliasName
     * @param string $sourceIdentifier
     * @param string $storeName
     * @param string $targetAnalyzerName
     * @param string $inputText
     * @param array<\Generated\Shared\Transfer\SearchAnalyzerConfigPreviewStageTransfer> $beforeStages
     *
     * @return array<\Generated\Shared\Transfer\SearchAnalyzerConfigPreviewStageTransfer>
     */
    protected function buildAfterStages(
        Client $elasticaClient,
        string $aliasName,
        string $sourceIdentifier,
        string $storeName,
        string $targetAnalyzerName,
        string $inputText,
        array $beforeStages,
    ): array {
        $searchAnalyzerConfigTransfer = $this->repository->findByScope($sourceIdentifier, $storeName);

        if ($searchAnalyzerConfigTransfer === null) {
            return $beforeStages;
        }

        $baseSettings = $this->readLiveAnalysisSettings($elasticaClient, $aliasName);
        $afterSettings = $this->renderer->render($searchAnalyzerConfigTransfer, $baseSettings);

        if ($afterSettings === $baseSettings) {
            return $beforeStages;
        }

        $previewIndex = $elasticaClient->getIndex($this->buildPreviewIndexName($aliasName));

        try {
            $previewIndex->create(['settings' => ['analysis' => $afterSettings['analysis'] ?? []]]);

            return $this->analyzeStages($previewIndex, $targetAnalyzerName, $inputText);
        } finally {
            $this->deleteIndexIfExistsQuietly($previewIndex);
        }
    }

    /**
     * @param string $sourceIdentifier
     * @param string $storeName
     *
     * @throws \SprykerCommunity\Zed\SearchAnalyzerConfig\Business\Exception\SearchAnalyzerConfigScopeNotManagedException
     */
    protected function resolveAliasName(string $sourceIdentifier, string $storeName): string
    {
        $searchIndexScopeTransfer = SearchIndexManagedScopeMatcher::match(
            $this->searchIndexAliasFacade->getManagedScopes(),
            $sourceIdentifier,
            $storeName,
        );

        if ($searchIndexScopeTransfer === null) {
            throw new SearchAnalyzerConfigScopeNotManagedException(sprintf(
                'No search-index-alias managed scope found for source "%s" / store "%s".',
                $sourceIdentifier,
                $storeName,
            ));
        }

        return $searchIndexScopeTransfer->getAliasNameOrFail();
    }

    /**
     * An alias can briefly resolve to more than one physical index mid-rollout (the outgoing index during
     * a blue/green cutover) -- `reset($response)` on the raw `_settings` response would pick whichever key
     * happens to sort first, an arbitrary choice that could silently read the stale outgoing index's
     * settings instead of the real current one. The most-recently-created index (by OpenSearch's own
     * `creation_date`, always present, no naming-convention assumption needed) is the deterministic,
     * unambiguous choice regardless of how many indices the alias currently spans.
     *
     * @param \Elastica\Client $elasticaClient
     * @param string $aliasName
     *
     * @return array<string, mixed>
     */
    protected function readLiveAnalysisSettings(Client $elasticaClient, string $aliasName): array
    {
        $response = $elasticaClient->request(sprintf('%s/_settings', $aliasName))->getData();
        $realIndexSettings = $this->selectNewestIndexSettings($response);

        return [
            'analysis' => $realIndexSettings['settings']['index']['analysis'] ?? [],
        ];
    }

    /**
     * @param array<string, array<string, mixed>> $indexSettingsByIndexName
     *
     * @return array<string, mixed>
     */
    protected function selectNewestIndexSettings(array $indexSettingsByIndexName): array
    {
        $newestIndexSettings = [];
        $newestCreationDateMillis = -1;

        foreach ($indexSettingsByIndexName as $indexSettings) {
            $creationDateMillis = (int)($indexSettings['settings']['index']['creation_date'] ?? 0);

            if ($creationDateMillis <= $newestCreationDateMillis) {
                continue;
            }

            $newestCreationDateMillis = $creationDateMillis;
            $newestIndexSettings = $indexSettings;
        }

        return $newestIndexSettings;
    }

    /**
     * @param string $aliasName
     */
    protected function buildPreviewIndexName(string $aliasName): string
    {
        return sprintf('%s%s_%d_%s', static::PREVIEW_INDEX_PREFIX, $aliasName, time(), substr(uniqid(), -6));
    }

    /**
     * @param \Elastica\Index $index
     * @param string $analyzerName
     * @param string $inputText
     *
     * @return array<\Generated\Shared\Transfer\SearchAnalyzerConfigPreviewStageTransfer>
     */
    protected function analyzeStages(Index $index, string $analyzerName, string $inputText): array
    {
        $detail = $index->analyze(['analyzer' => $analyzerName, 'text' => $inputText, 'explain' => true]);

        return $this->mapStages($detail);
    }

    /**
     * @param array<string, mixed> $detail
     *
     * @return array<\Generated\Shared\Transfer\SearchAnalyzerConfigPreviewStageTransfer>
     */
    protected function mapStages(array $detail): array
    {
        $stages = [];

        foreach ((array)($detail['charfilters'] ?? []) as $charFilter) {
            $filteredText = (string)(($charFilter['filtered_text'] ?? [])[0] ?? '');
            $stages[] = $this->buildStage('char filter: ' . ($charFilter['name'] ?? '?'), [$filteredText]);
        }

        $tokenizerTokens = $this->extractTokenStrings($detail['tokenizer']['tokens'] ?? []);

        if ($tokenizerTokens !== []) {
            $stages[] = $this->buildStage('tokenizer: ' . ($detail['tokenizer']['name'] ?? '?'), $tokenizerTokens);
        }

        foreach ((array)($detail['tokenfilters'] ?? []) as $tokenFilter) {
            // Deliberately NOT skipped when empty -- a stage that removed every surviving token (e.g. a
            // stop-word list wiping out a one-word query) is a real, meaningful result, not a display bug.
            $stages[] = $this->buildStage('filter: ' . ($tokenFilter['name'] ?? '?'), $this->extractTokenStrings($tokenFilter['tokens'] ?? []));
        }

        if ($stages === [] && isset($detail['analyzer']['tokens'])) {
            $stages[] = $this->buildStage(
                'analyzer: ' . ($detail['analyzer']['name'] ?? '?'),
                $this->extractTokenStrings($detail['analyzer']['tokens']),
            );
        }

        return $stages;
    }

    /**
     * @param string $stageName
     * @param array<string> $tokens
     */
    protected function buildStage(string $stageName, array $tokens): SearchAnalyzerConfigPreviewStageTransfer
    {
        $searchAnalyzerConfigPreviewStageTransfer = new SearchAnalyzerConfigPreviewStageTransfer();
        $searchAnalyzerConfigPreviewStageTransfer->setStageName($stageName);

        foreach ($tokens as $token) {
            $searchAnalyzerConfigPreviewStageTransfer->addToken($token);
        }

        return $searchAnalyzerConfigPreviewStageTransfer;
    }

    /**
     * @param array<int, array<string, mixed>> $rawTokens
     *
     * @return array<string>
     */
    protected function extractTokenStrings(array $rawTokens): array
    {
        $tokens = [];

        foreach ($rawTokens as $rawToken) {
            if (!isset($rawToken['token'])) {
                continue;
            }

            $tokens[] = (string)$rawToken['token'];
        }

        return $tokens;
    }

    /**
     * @param \Elastica\Client $elasticaClient
     *
     * @return array<string>
     */
    protected function findPreviewIndexNames(Client $elasticaClient): array
    {
        $aliasesResponse = $elasticaClient->request('_aliases')->getData();

        return array_values(array_filter(
            array_keys($aliasesResponse),
            static fn (string $indexName): bool => str_starts_with($indexName, static::PREVIEW_INDEX_PREFIX),
        ));
    }

    /**
     * @param \Elastica\Client $elasticaClient
     * @param string $indexName
     */
    protected function getIndexAgeSeconds(Client $elasticaClient, string $indexName): ?int
    {
        $response = $elasticaClient->request(sprintf('%s/_settings', $indexName))->getData();
        $creationDateMillis = $response[$indexName]['settings']['index']['creation_date'] ?? null;

        if ($creationDateMillis === null) {
            return null;
        }

        return (int)(time() - ((int)$creationDateMillis / 1000));
    }
}
