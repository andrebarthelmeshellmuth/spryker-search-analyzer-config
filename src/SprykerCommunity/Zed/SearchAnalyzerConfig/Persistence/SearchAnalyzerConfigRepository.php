<?php

/**
 * This file is part of the spryker-community/search-analyzer-config package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchAnalyzerConfig\Persistence;

use ArrayObject;
use Generated\Shared\Transfer\SearchAnalyzerConfigRevisionTransfer;
use Generated\Shared\Transfer\SearchAnalyzerConfigTermTransfer;
use Generated\Shared\Transfer\SearchAnalyzerConfigTransfer;
use Orm\Zed\SearchAnalyzerConfig\Persistence\SpySearchAnalyzerConfigRevision;
use Propel\Runtime\ActiveQuery\Criteria;
use Spryker\Zed\Kernel\Persistence\AbstractRepository;
use SprykerCommunity\Shared\SearchAnalyzerConfig\SearchAnalyzerConfigConfig;

/**
 * @method \SprykerCommunity\Zed\SearchAnalyzerConfig\Persistence\SearchAnalyzerConfigPersistenceFactory getFactory()
 */
class SearchAnalyzerConfigRepository extends AbstractRepository implements SearchAnalyzerConfigRepositoryInterface
{
    /**
     * @param string $sourceIdentifier
     * @param string $storeName
     */
    public function findByScope(string $sourceIdentifier, string $storeName): ?SearchAnalyzerConfigTransfer
    {
        $spySearchAnalyzerConfig = $this->getFactory()
            ->createSpySearchAnalyzerConfigQuery()
            ->filterBySourceIdentifier($sourceIdentifier)
            ->filterByStoreName($storeName)
            ->findOne();

        if ($spySearchAnalyzerConfig === null) {
            return null;
        }

        $searchAnalyzerConfigTransfer = $this->getFactory()
            ->createSearchAnalyzerConfigMapper()
            ->mapEntityToTransfer($spySearchAnalyzerConfig, new SearchAnalyzerConfigTransfer());

        $termsByListType = $this->findTermsByListType($spySearchAnalyzerConfig->getIdSearchAnalyzerConfig());

        return $searchAnalyzerConfigTransfer
            ->setDecompoundWords($termsByListType[SearchAnalyzerConfigConfig::LIST_TYPE_DECOMPOUND_WORD])
            ->setSynonyms($termsByListType[SearchAnalyzerConfigConfig::LIST_TYPE_SYNONYM])
            ->setStopwords($termsByListType[SearchAnalyzerConfigConfig::LIST_TYPE_STOPWORD])
            ->setDoNotDecompoundTerms($termsByListType[SearchAnalyzerConfigConfig::LIST_TYPE_DO_NOT_DECOMPOUND]);
    }

    /**
     * Newest first. Empty when the scope has never been saved -- a revision row is only ever written by
     * a save (see SearchAnalyzerConfigEntityManager::saveSearchAnalyzerConfig()), so an unstaged scope
     * has none.
     *
     * @param string $sourceIdentifier
     * @param string $storeName
     *
     * @return array<\Generated\Shared\Transfer\SearchAnalyzerConfigRevisionTransfer>
     */
    public function getRevisionHistory(string $sourceIdentifier, string $storeName): array
    {
        $spySearchAnalyzerConfig = $this->getFactory()
            ->createSpySearchAnalyzerConfigQuery()
            ->filterBySourceIdentifier($sourceIdentifier)
            ->filterByStoreName($storeName)
            ->findOne();

        if ($spySearchAnalyzerConfig === null) {
            return [];
        }

        $spySearchAnalyzerConfigRevisions = $this->getFactory()
            ->createSpySearchAnalyzerConfigRevisionQuery()
            ->filterByFkSearchAnalyzerConfig($spySearchAnalyzerConfig->getIdSearchAnalyzerConfig())
            ->orderByRevision(Criteria::DESC)
            ->find();

        $searchAnalyzerConfigRevisionTransfers = [];

        foreach ($spySearchAnalyzerConfigRevisions as $spySearchAnalyzerConfigRevision) {
            $searchAnalyzerConfigRevisionTransfers[] = $this->mapRevisionEntityToTransfer(
                $spySearchAnalyzerConfigRevision,
                $sourceIdentifier,
                $storeName,
            );
        }

        return $searchAnalyzerConfigRevisionTransfers;
    }

    /**
     * The inverse of SearchAnalyzerConfigEntityManager::writeRevisionSnapshot() -- decodes one JSON
     * snapshot back into the same SearchAnalyzerConfigTransfer shape the rest of this package works with.
     * sourceIdentifier/storeName are NOT part of the snapshot itself (see that method's own doc block --
     * the snapshot only holds the scope's editable columns and term lists), so the caller's own scope is
     * threaded back in here.
     *
     * @param \Orm\Zed\SearchAnalyzerConfig\Persistence\SpySearchAnalyzerConfigRevision $spySearchAnalyzerConfigRevision
     * @param string $sourceIdentifier
     * @param string $storeName
     */
    protected function mapRevisionEntityToTransfer(
        SpySearchAnalyzerConfigRevision $spySearchAnalyzerConfigRevision,
        string $sourceIdentifier,
        string $storeName,
    ): SearchAnalyzerConfigRevisionTransfer {
        /** @var array<string, mixed> $snapshot */
        $snapshot = (array)json_decode((string)$spySearchAnalyzerConfigRevision->getSnapshot(), true);

        $searchAnalyzerConfigTransfer = (new SearchAnalyzerConfigTransfer())
            ->setSourceIdentifier($sourceIdentifier)
            ->setStoreName($storeName)
            ->setStemmerLanguage($snapshot['stemmerLanguage'] ?? null)
            ->setNormalizationFilter($snapshot['normalizationFilter'] ?? null)
            ->setStopwordsMode($snapshot['stopwordsMode'] ?? null)
            ->setStopwordsBuiltinLanguage($snapshot['stopwordsBuiltinLanguage'] ?? null)
            ->setDecompoundEnabled((bool)($snapshot['decompoundEnabled'] ?? false))
            ->setDecompoundWords($this->buildTermCollection((array)($snapshot['decompoundWords'] ?? []), SearchAnalyzerConfigConfig::LIST_TYPE_DECOMPOUND_WORD))
            ->setSynonyms($this->buildTermCollection((array)($snapshot['synonyms'] ?? []), SearchAnalyzerConfigConfig::LIST_TYPE_SYNONYM))
            ->setStopwords($this->buildTermCollection((array)($snapshot['stopwords'] ?? []), SearchAnalyzerConfigConfig::LIST_TYPE_STOPWORD))
            ->setDoNotDecompoundTerms($this->buildTermCollection((array)($snapshot['doNotDecompoundTerms'] ?? []), SearchAnalyzerConfigConfig::LIST_TYPE_DO_NOT_DECOMPOUND))
            ->setRevision($spySearchAnalyzerConfigRevision->getRevision());

        return (new SearchAnalyzerConfigRevisionTransfer())
            ->setIdSearchAnalyzerConfigRevision($spySearchAnalyzerConfigRevision->getIdSearchAnalyzerConfigRevision())
            ->setRevision($spySearchAnalyzerConfigRevision->getRevision())
            ->setChangeSource($spySearchAnalyzerConfigRevision->getChangeSource())
            ->setSearchAnalyzerConfig($searchAnalyzerConfigTransfer)
            ->setTriggeredByUser($spySearchAnalyzerConfigRevision->getTriggeredByUser())
            ->setCreatedAt($spySearchAnalyzerConfigRevision->getCreatedAt() ? $spySearchAnalyzerConfigRevision->getCreatedAt()->format(DATE_ATOM) : null);
    }

    /**
     * @param array<string> $terms
     * @param string $listType
     *
     * @return \ArrayObject<int, \Generated\Shared\Transfer\SearchAnalyzerConfigTermTransfer>
     */
    protected function buildTermCollection(array $terms, string $listType): ArrayObject
    {
        $sortOrder = 0;
        $searchAnalyzerConfigTermTransfers = [];

        foreach ($terms as $term) {
            $searchAnalyzerConfigTermTransfers[] = (new SearchAnalyzerConfigTermTransfer())
                ->setListType($listType)
                ->setTerm((string)$term)
                ->setSortOrder($sortOrder++);
        }

        return new ArrayObject($searchAnalyzerConfigTermTransfers);
    }

    /**
     * @param int $idSearchAnalyzerConfig
     *
     * @return array<string, \ArrayObject<int, \Generated\Shared\Transfer\SearchAnalyzerConfigTermTransfer>>
     */
    protected function findTermsByListType(int $idSearchAnalyzerConfig): array
    {
        $termsByListType = [];

        foreach (SearchAnalyzerConfigConfig::LIST_TYPES as $listType) {
            $termsByListType[$listType] = [];
        }

        $spySearchAnalyzerConfigTerms = $this->getFactory()
            ->createSpySearchAnalyzerConfigTermQuery()
            ->filterByFkSearchAnalyzerConfig($idSearchAnalyzerConfig)
            ->orderBySortOrder()
            ->find();

        $mapper = $this->getFactory()->createSearchAnalyzerConfigMapper();

        foreach ($spySearchAnalyzerConfigTerms as $spySearchAnalyzerConfigTerm) {
            $listType = $spySearchAnalyzerConfigTerm->getListType();

            if (!isset($termsByListType[$listType])) {
                continue;
            }

            $termsByListType[$listType][] = $mapper->mapTermEntityToTransfer(
                $spySearchAnalyzerConfigTerm,
                new SearchAnalyzerConfigTermTransfer(),
            );
        }

        return array_map(static fn (array $terms): ArrayObject => new ArrayObject($terms), $termsByListType);
    }
}
