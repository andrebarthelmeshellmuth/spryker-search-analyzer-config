<?php

/**
 * This file is part of the spryker-community/search-analyzer-config package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Zed\SearchAnalyzerConfig\Business\Previewer;

use ArrayObject;
use Codeception\Test\Unit;
use Elastica\Client;
use Generated\Shared\Transfer\SearchAnalyzerConfigTermTransfer;
use Generated\Shared\Transfer\SearchAnalyzerConfigTransfer;
use Generated\Shared\Transfer\SearchIndexRolloutTransfer;
use Generated\Shared\Transfer\SearchIndexScopeTransfer;
use LogicException;
use Spryker\Zed\SearchElasticsearch\SearchElasticsearchConfig;
use SprykerCommunity\Zed\SearchAnalyzerConfig\Business\Client\ElasticaClientProvider;
use SprykerCommunity\Zed\SearchAnalyzerConfig\Business\Previewer\SearchAnalyzerConfigPreviewer;
use SprykerCommunity\Zed\SearchAnalyzerConfig\Business\Renderer\SearchAnalyzerConfigRenderer;
use SprykerCommunity\Zed\SearchAnalyzerConfig\Dependency\Facade\SearchAnalyzerConfigToSearchIndexAliasFacadeInterface;
use SprykerCommunity\Zed\SearchAnalyzerConfig\Persistence\SearchAnalyzerConfigRepository;
use stdClass;

/**
 * INTEGRATION TEST — real OpenSearch/Elasticsearch cluster, never mocked: proves both methods documented on
 * `SearchAnalyzerConfigPreviewer`. This package never adds a filter to an analyzer's own chain or reorders
 * it (see SearchAnalyzerConfigRenderer's class doc block), so each test's fixture index pre-declares the
 * `sac_synonyms`/`sac_stemmer` slots itself, exactly the way a real project schema would. Which analyzer(s)
 * count as targets is auto-discovered from the live index (any analyzer chain referencing at least one
 * `sac_*` slot -- see SearchAnalyzerConfigRendererInterface::resolveTargetAnalyzerNames()), so each test
 * builds its own single-analyzer index rather than sharing one fixture across scenarios, to keep the
 * discovered analyzer set unambiguous per test. A slot never referenced by the target analyzer is a
 * deliberate per-analyzer opt-out now, not a save-blocking error -- `collectMissingSlotWarnings()` is the
 * cheap, read-only, non-fatal check for that case, meant for a caller that wants to warn the user and let
 * them confirm past it; `validateAgainstLiveCluster()` no longer blocks on it at all, only on the expensive
 * create-and-delete live-cluster probe (the backstop for a slot that IS chained and correctly positioned,
 * but whose DATA OpenSearch itself rejects -- an unrecognized stemmer language, here -- verified live). A
 * slot the PROJECT positioned badly (e.g. a synonym filter after its own n-gram filter) can't be exercised
 * this way at all: OpenSearch refuses to even CREATE an index with that combination, structural content
 * aside -- i.e. that mistake already breaks the project's next blue/green rebuild before this package's own
 * validation ever runs, exactly as intended. Uses its OWN throwaway alias/index with hand-built analyzers
 * (not the demoshop's real `page` scope), so this suite never depends on or mutates real project data.
 *
 * Auto-generated group annotations
 *
 * @group SprykerCommunityTest
 * @group Zed
 * @group SearchAnalyzerConfig
 * @group Business
 * @group Previewer
 * @group SearchAnalyzerConfigPreviewerTest
 * Add your own group annotations below this line
 * @group NeedsDatabase
 */
class SearchAnalyzerConfigPreviewerTest extends Unit
{
    /**
     * @var string
     */
    protected const TEST_INDEX = 'phpunit_sac_previewer_test_index';

    /**
     * @var string
     */
    protected const TEST_ALIAS = 'phpunit_sac_previewer_test_alias';

    /**
     * Both slots referenced, correctly positioned (sac_synonyms ahead of the project's own n-gram filter).
     *
     * @var string
     */
    protected const ANALYZER_WITH_CORRECTLY_POSITIONED_SLOTS = 'phpunit_valid_analyzer';

    /**
     * References sac_stemmer (so it's still auto-discovered as a target) but deliberately NOT sac_synonyms.
     *
     * @var string
     */
    protected const ANALYZER_WITHOUT_SYNONYMS_SLOT = 'phpunit_missing_slot_analyzer';

    protected function _before(): void
    {
        $this->deleteTestIndexIfExists();
    }

    protected function _after(): void
    {
        $this->deleteTestIndexIfExists();
    }

    protected function deleteTestIndexIfExists(): void
    {
        $index = $this->getElasticaClient()->getIndex(static::TEST_INDEX);

        if (!$index->exists()) {
            return;
        }

        $index->delete();
    }

    /**
     * @param array<string> $filterChain
     */
    protected function createIndex(string $analyzerName, array $filterChain): void
    {
        $this->getElasticaClient()->getIndex(static::TEST_INDEX)->create([
            'aliases' => [static::TEST_ALIAS => new stdClass()],
            'settings' => [
                'analysis' => [
                    'filter' => [
                        'phpunit_ngram_filter' => ['type' => 'edge_ngram', 'min_gram' => 2, 'max_gram' => 20],
                        'sac_synonyms' => ['type' => 'synonym', 'synonyms' => []],
                        'sac_stemmer' => ['type' => 'stemmer', 'language' => 'light_german'],
                    ],
                    'analyzer' => [
                        $analyzerName => [
                            'tokenizer' => 'standard',
                            'filter' => $filterChain,
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function testSucceedsWhenTheSlotIsReferencedAndTheProjectPositionedItCorrectly(): void
    {
        $this->createIndex(static::ANALYZER_WITH_CORRECTLY_POSITIONED_SLOTS, ['lowercase', 'sac_synonyms', 'sac_stemmer', 'phpunit_ngram_filter']);
        $previewer = $this->createPreviewer();

        $errors = $previewer->validateAgainstLiveCluster('phpunit_source', 'PHPUNIT', $this->transferWithSynonyms());

        $this->assertSame([], $errors);
    }

    /**
     * A slot never referenced by the target analyzer is now a deliberate per-analyzer opt-out, not a hard
     * save-blocking error -- validateAgainstLiveCluster() no longer catches it at all. See
     * testCollectMissingSlotWarningsReportsTheSameCaseAsANonFatalWarning() for the same case surfaced
     * as a non-fatal warning instead.
     */
    public function testDoesNotFailWhenTheSlotIsNeverReferencedAtAll(): void
    {
        $this->createIndex(static::ANALYZER_WITHOUT_SYNONYMS_SLOT, ['lowercase', 'sac_stemmer']);
        $previewer = $this->createPreviewer();

        $errors = $previewer->validateAgainstLiveCluster('phpunit_source', 'PHPUNIT', $this->transferWithSynonyms());

        $this->assertSame([], $errors);
    }

    /**
     * Pre-save, non-fatal counterpart to the case above -- same detection, but returned as a warning the
     * caller (the Zed edit form) can let the user confirm past, not a save-blocking error.
     */
    public function testCollectMissingSlotWarningsReportsTheSameCaseAsANonFatalWarning(): void
    {
        $this->createIndex(static::ANALYZER_WITHOUT_SYNONYMS_SLOT, ['lowercase', 'sac_stemmer']);
        $previewer = $this->createPreviewer();

        $warnings = $previewer->collectMissingSlotWarnings('phpunit_source', 'PHPUNIT', $this->transferWithSynonyms());

        $this->assertNotEmpty($warnings);
        $this->assertStringContainsString('sac_synonyms', $warnings[0]);
    }

    public function testCollectMissingSlotWarningsIsEmptyWhenEveryReferencedSlotIsPresent(): void
    {
        $this->createIndex(static::ANALYZER_WITH_CORRECTLY_POSITIONED_SLOTS, ['lowercase', 'sac_synonyms', 'sac_stemmer', 'phpunit_ngram_filter']);
        $previewer = $this->createPreviewer();

        $warnings = $previewer->collectMissingSlotWarnings('phpunit_source', 'PHPUNIT', $this->transferWithSynonyms());

        $this->assertSame([], $warnings);
    }

    /**
     * The slot IS chained and correctly positioned (structural check passes), but the DATA itself is
     * something OpenSearch rejects -- an unrecognized stemmer language. Nothing left for this package to
     * fix; the live-cluster probe is the only thing that can catch this, exactly the way it would fail on a
     * real Apply/rebuild.
     */
    public function testStillRejectsWhenTheSlotExistsButTheDataItselfIsInvalid(): void
    {
        $this->createIndex(static::ANALYZER_WITH_CORRECTLY_POSITIONED_SLOTS, ['lowercase', 'sac_synonyms', 'sac_stemmer', 'phpunit_ngram_filter']);
        $previewer = $this->createPreviewer();

        $searchAnalyzerConfigTransfer = (new SearchAnalyzerConfigTransfer())
            ->setSourceIdentifier('phpunit_source')
            ->setStoreName('PHPUNIT')
            ->setStemmerLanguage('not_a_real_language');

        $errors = $previewer->validateAgainstLiveCluster('phpunit_source', 'PHPUNIT', $searchAnalyzerConfigTransfer);

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('not compatible', $errors[0]);
    }

    /**
     * Two-layer design: the expensive create-and-delete probe only runs when something would actually
     * change. An empty config against a slot that's already empty changes nothing, so the probe is skipped.
     */
    public function testSkipsTheLiveClusterProbeEntirelyWhenNothingWouldActuallyChange(): void
    {
        $this->createIndex(static::ANALYZER_WITH_CORRECTLY_POSITIONED_SLOTS, ['lowercase', 'sac_synonyms', 'sac_stemmer', 'phpunit_ngram_filter']);
        $previewer = $this->createPreviewer();

        $errors = $previewer->validateAgainstLiveCluster('phpunit_source', 'PHPUNIT', new SearchAnalyzerConfigTransfer());

        $this->assertSame([], $errors);
    }

    public function testLeavesNoThrowawayIndexBehindEitherWay(): void
    {
        $this->createIndex(static::ANALYZER_WITH_CORRECTLY_POSITIONED_SLOTS, ['lowercase', 'sac_synonyms', 'sac_stemmer', 'phpunit_ngram_filter']);
        $previewer = $this->createPreviewer();
        $previewer->validateAgainstLiveCluster('phpunit_source', 'PHPUNIT', $this->transferWithSynonyms());

        $orphaned = $previewer->pruneOrphanedPreviewIndices();

        $this->assertSame([], $orphaned, 'The probe index must already be gone via its own finally block, not merely prunable later.');
    }

    protected function createPreviewer(): SearchAnalyzerConfigPreviewer
    {
        return new SearchAnalyzerConfigPreviewer(
            new SearchAnalyzerConfigRepository(),
            new SearchAnalyzerConfigRenderer(),
            $this->createSearchIndexAliasFacadeStub(),
            new ElasticaClientProvider(new SearchElasticsearchConfig()),
        );
    }

    protected function createSearchIndexAliasFacadeStub(): SearchAnalyzerConfigToSearchIndexAliasFacadeInterface
    {
        $searchIndexScopeTransfer = (new SearchIndexScopeTransfer())
            ->setSourceIdentifier('phpunit_source')
            ->setStoreName('PHPUNIT')
            ->setAliasName(static::TEST_ALIAS);

        return new class ($searchIndexScopeTransfer) implements SearchAnalyzerConfigToSearchIndexAliasFacadeInterface {
            public function __construct(protected SearchIndexScopeTransfer $searchIndexScopeTransfer)
            {
            }

            /**
             * @return array<\Generated\Shared\Transfer\SearchIndexScopeTransfer>
             */
            public function getManagedScopes(): array
            {
                return [$this->searchIndexScopeTransfer];
            }

            /**
             * @phpcsSuppress SlevomatCodingStandard.Functions.UnusedParameter Interface-mandated signature; this stub is never called by validateAgainstLiveCluster(), which is the only method this test exercises.
             *
             * @param \Generated\Shared\Transfer\SearchIndexScopeTransfer $searchIndexScopeTransfer
             * @param string|null $triggeredByUser
             * @param array<string, mixed>|null $targetMappingProperties
             * @param bool $optimizeForBulkLoad
             *
             * @throws \LogicException
             */
            public function requestRebuildAsync(
                SearchIndexScopeTransfer $searchIndexScopeTransfer,
                ?string $triggeredByUser = null,
                ?array $targetMappingProperties = null,
                bool $optimizeForBulkLoad = false,
            ): SearchIndexRolloutTransfer {
                throw new LogicException('Not needed by this test.');
            }
        };
    }

    protected function transferWithSynonyms(): SearchAnalyzerConfigTransfer
    {
        return (new SearchAnalyzerConfigTransfer())
            ->setSourceIdentifier('phpunit_source')
            ->setStoreName('PHPUNIT')
            ->setSynonyms(new ArrayObject([(new SearchAnalyzerConfigTermTransfer())->setTerm('sofa, couch')]));
    }

    protected function getElasticaClient(): Client
    {
        return (new ElasticaClientProvider(new SearchElasticsearchConfig()))->getClient();
    }
}
