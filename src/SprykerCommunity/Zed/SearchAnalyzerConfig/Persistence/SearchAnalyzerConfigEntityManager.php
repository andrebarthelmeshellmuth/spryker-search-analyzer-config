<?php

/**
 * This file is part of the spryker-community/search-analyzer-config package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchAnalyzerConfig\Persistence;

use DateTime;
use Generated\Shared\Transfer\SearchAnalyzerConfigTransfer;
use Orm\Zed\SearchAnalyzerConfig\Persistence\SpySearchAnalyzerConfigRevision;
use Orm\Zed\SearchAnalyzerConfig\Persistence\SpySearchAnalyzerConfigTerm;
use Spryker\Zed\Kernel\Persistence\AbstractEntityManager;
use Spryker\Zed\Kernel\Persistence\EntityManager\TransactionTrait;
use SprykerCommunity\Shared\SearchAnalyzerConfig\SearchAnalyzerConfigConfig;

/**
 * @method \SprykerCommunity\Zed\SearchAnalyzerConfig\Persistence\SearchAnalyzerConfigPersistenceFactory getFactory()
 */
class SearchAnalyzerConfigEntityManager extends AbstractEntityManager implements SearchAnalyzerConfigEntityManagerInterface
{
    use TransactionTrait;

    /**
     * Wrapped in one DB transaction -- the parent row, its term rows, and its revision snapshot must all
     * land together or not at all, otherwise a crash between steps desyncs the live config from its own
     * history. `lockForUpdate()` on the parent-row read closes the matching race: without it, two
     * concurrent saves for the same scope could both read the same `revision`, both compute the same
     * `nextRevision`, and silently overwrite each other while writing duplicate revision-history rows.
     *
     * @param \Generated\Shared\Transfer\SearchAnalyzerConfigTransfer $searchAnalyzerConfigTransfer
     * @param string $changeSource
     * @param string|null $triggeredByUser
     */
    public function saveSearchAnalyzerConfig(
        SearchAnalyzerConfigTransfer $searchAnalyzerConfigTransfer,
        string $changeSource,
        ?string $triggeredByUser,
    ): SearchAnalyzerConfigTransfer {
        $searchAnalyzerConfigTransfer->requireSourceIdentifier()->requireStoreName();

        return $this->getTransactionHandler()->handleTransaction(
            function () use ($searchAnalyzerConfigTransfer, $changeSource, $triggeredByUser): SearchAnalyzerConfigTransfer {
                $spySearchAnalyzerConfig = $this->getFactory()
                    ->createSpySearchAnalyzerConfigQuery()
                    ->lockForUpdate()
                    ->filterBySourceIdentifier($searchAnalyzerConfigTransfer->getSourceIdentifierOrFail())
                    ->filterByStoreName($searchAnalyzerConfigTransfer->getStoreNameOrFail())
                    ->findOneOrCreate();

                $nextRevision = (int)$spySearchAnalyzerConfig->getRevision() + 1;

                $spySearchAnalyzerConfig
                    ->setSourceIdentifier($searchAnalyzerConfigTransfer->getSourceIdentifierOrFail())
                    ->setStoreName($searchAnalyzerConfigTransfer->getStoreNameOrFail())
                    ->setStemmerLanguage($searchAnalyzerConfigTransfer->getStemmerLanguage())
                    ->setNormalizationFilter($searchAnalyzerConfigTransfer->getNormalizationFilter())
                    ->setStopwordsMode($searchAnalyzerConfigTransfer->getStopwordsMode() ?? SearchAnalyzerConfigConfig::STOPWORDS_MODE_NONE)
                    ->setStopwordsBuiltinLanguage($searchAnalyzerConfigTransfer->getStopwordsBuiltinLanguage())
                    ->setDecompoundEnabled((bool)$searchAnalyzerConfigTransfer->getDecompoundEnabled())
                    ->setRevision($nextRevision)
                    ->save();

                $this->replaceTerms($spySearchAnalyzerConfig->getIdSearchAnalyzerConfig(), $searchAnalyzerConfigTransfer);

                $searchAnalyzerConfigTransfer->setIdSearchAnalyzerConfig($spySearchAnalyzerConfig->getIdSearchAnalyzerConfig());
                $searchAnalyzerConfigTransfer->setRevision($nextRevision);

                $this->writeRevisionSnapshot($spySearchAnalyzerConfig->getIdSearchAnalyzerConfig(), $nextRevision, $changeSource, $triggeredByUser, $searchAnalyzerConfigTransfer);

                return $searchAnalyzerConfigTransfer;
            },
        );
    }

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
    ): void {
        $spySearchAnalyzerConfig = $this->getFactory()
            ->createSpySearchAnalyzerConfigQuery()
            ->filterBySourceIdentifier($sourceIdentifier)
            ->filterByStoreName($storeName)
            ->findOne();

        if ($spySearchAnalyzerConfig === null) {
            return;
        }

        $spySearchAnalyzerConfig
            ->setAppliedRevision($revision)
            ->setAppliedIndexName($appliedIndexName)
            ->setAppliedAt(new DateTime())
            ->save();
    }

    /**
     * Full-replace: every existing term row for this scope is deleted, then the transfer's own lists are
     * re-inserted in order. See the interface's own doc block for why this is deliberately not a diff.
     *
     * @param int $idSearchAnalyzerConfig
     * @param \Generated\Shared\Transfer\SearchAnalyzerConfigTransfer $searchAnalyzerConfigTransfer
     */
    protected function replaceTerms(int $idSearchAnalyzerConfig, SearchAnalyzerConfigTransfer $searchAnalyzerConfigTransfer): void
    {
        $this->getFactory()
            ->createSpySearchAnalyzerConfigTermQuery()
            ->filterByFkSearchAnalyzerConfig($idSearchAnalyzerConfig)
            ->delete();

        $termLists = [
            'decompound_word' => $searchAnalyzerConfigTransfer->getDecompoundWords(),
            'synonym' => $searchAnalyzerConfigTransfer->getSynonyms(),
            'stopword' => $searchAnalyzerConfigTransfer->getStopwords(),
            'do_not_decompound' => $searchAnalyzerConfigTransfer->getDoNotDecompoundTerms(),
        ];

        foreach ($termLists as $listType => $searchAnalyzerConfigTermTransfers) {
            $sortOrder = 0;

            foreach ($searchAnalyzerConfigTermTransfers as $searchAnalyzerConfigTermTransfer) {
                /** @var \Generated\Shared\Transfer\SearchAnalyzerConfigTermTransfer $searchAnalyzerConfigTermTransfer */
                $term = trim((string)$searchAnalyzerConfigTermTransfer->getTerm());

                if ($term === '') {
                    continue;
                }

                (new SpySearchAnalyzerConfigTerm())
                    ->setFkSearchAnalyzerConfig($idSearchAnalyzerConfig)
                    ->setListType($listType)
                    ->setTerm($term)
                    ->setSortOrder($sortOrder++)
                    ->save();
            }
        }
    }

    /**
     * @param int $idSearchAnalyzerConfig
     * @param int $revision
     * @param string $changeSource
     * @param string|null $triggeredByUser
     * @param \Generated\Shared\Transfer\SearchAnalyzerConfigTransfer $searchAnalyzerConfigTransfer
     */
    protected function writeRevisionSnapshot(
        int $idSearchAnalyzerConfig,
        int $revision,
        string $changeSource,
        ?string $triggeredByUser,
        SearchAnalyzerConfigTransfer $searchAnalyzerConfigTransfer,
    ): void {
        $snapshot = [
            'stemmerLanguage' => $searchAnalyzerConfigTransfer->getStemmerLanguage(),
            'normalizationFilter' => $searchAnalyzerConfigTransfer->getNormalizationFilter(),
            'stopwordsMode' => $searchAnalyzerConfigTransfer->getStopwordsMode(),
            'stopwordsBuiltinLanguage' => $searchAnalyzerConfigTransfer->getStopwordsBuiltinLanguage(),
            'decompoundEnabled' => $searchAnalyzerConfigTransfer->getDecompoundEnabled(),
            'decompoundWords' => $this->extractTermStrings($searchAnalyzerConfigTransfer->getDecompoundWords()),
            'synonyms' => $this->extractTermStrings($searchAnalyzerConfigTransfer->getSynonyms()),
            'stopwords' => $this->extractTermStrings($searchAnalyzerConfigTransfer->getStopwords()),
            'doNotDecompoundTerms' => $this->extractTermStrings($searchAnalyzerConfigTransfer->getDoNotDecompoundTerms()),
        ];

        (new SpySearchAnalyzerConfigRevision())
            ->setFkSearchAnalyzerConfig($idSearchAnalyzerConfig)
            ->setRevision($revision)
            ->setChangeSource($changeSource)
            ->setSnapshot(json_encode($snapshot, JSON_THROW_ON_ERROR))
            ->setTriggeredByUser($triggeredByUser)
            ->save();
    }

    /**
     * @param iterable<\Generated\Shared\Transfer\SearchAnalyzerConfigTermTransfer> $searchAnalyzerConfigTermTransfers
     *
     * @return array<string>
     */
    protected function extractTermStrings(iterable $searchAnalyzerConfigTermTransfers): array
    {
        $terms = [];

        foreach ($searchAnalyzerConfigTermTransfers as $searchAnalyzerConfigTermTransfer) {
            /** @var \Generated\Shared\Transfer\SearchAnalyzerConfigTermTransfer $searchAnalyzerConfigTermTransfer */
            $terms[] = (string)$searchAnalyzerConfigTermTransfer->getTerm();
        }

        return $terms;
    }
}
