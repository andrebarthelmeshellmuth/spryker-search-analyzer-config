<?php

/**
 * This file is part of the spryker-community/search-analyzer-config package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Zed\SearchAnalyzerConfig\Persistence;

use ArrayObject;
use Codeception\Test\Unit;
use Generated\Shared\Transfer\SearchAnalyzerConfigTermTransfer;
use Generated\Shared\Transfer\SearchAnalyzerConfigTransfer;
use Orm\Zed\SearchAnalyzerConfig\Persistence\SpySearchAnalyzerConfigQuery;
use SprykerCommunity\Shared\SearchAnalyzerConfig\SearchAnalyzerConfigConfig;
use SprykerCommunity\Zed\SearchAnalyzerConfig\Persistence\SearchAnalyzerConfigEntityManager;
use SprykerCommunity\Zed\SearchAnalyzerConfig\Persistence\SearchAnalyzerConfigRepository;

/**
 * INTEGRATION TEST — real database, real rows, never mocked.
 *
 * Auto-generated group annotations
 *
 * @group SprykerCommunityTest
 * @group Zed
 * @group SearchAnalyzerConfig
 * @group Persistence
 * @group SearchAnalyzerConfigRepositoryTest
 * Add your own group annotations below this line
 * @group NeedsDatabase
 */
class SearchAnalyzerConfigRepositoryTest extends Unit
{
    /**
     * @var string
     */
    protected const TEST_SOURCE_IDENTIFIER = 'phpunit_source';

    /**
     * @var string
     */
    protected const TEST_STORE_NAME = 'PHPUNIT';

    protected function _before(): void
    {
        $this->cleanUp();
    }

    protected function _after(): void
    {
        $this->cleanUp();
    }

    protected function cleanUp(): void
    {
        SpySearchAnalyzerConfigQuery::create()
            ->filterBySourceIdentifier(static::TEST_SOURCE_IDENTIFIER)
            ->filterByStoreName(static::TEST_STORE_NAME)
            ->delete();
    }

    public function testFindByScopeReturnsNullForAnUnstagedScope(): void
    {
        $result = (new SearchAnalyzerConfigRepository())->findByScope(static::TEST_SOURCE_IDENTIFIER, static::TEST_STORE_NAME);

        $this->assertNull($result);
    }

    public function testFindByScopeReturnsTheSavedConfigWithTermsGroupedByListType(): void
    {
        $searchAnalyzerConfigTransfer = (new SearchAnalyzerConfigTransfer())
            ->setSourceIdentifier(static::TEST_SOURCE_IDENTIFIER)
            ->setStoreName(static::TEST_STORE_NAME)
            ->setStemmerLanguage('light_german')
            ->setDecompoundEnabled(true)
            ->setDecompoundWords(new ArrayObject([$this->term('sofa')]))
            ->setSynonyms(new ArrayObject([$this->term('couch, sofa')]))
            ->setDoNotDecompoundTerms(new ArrayObject([$this->term('Brennenstuhl')]));

        (new SearchAnalyzerConfigEntityManager())->saveSearchAnalyzerConfig(
            $searchAnalyzerConfigTransfer,
            SearchAnalyzerConfigConfig::CHANGE_SOURCE_MANUAL,
            null,
        );

        $result = (new SearchAnalyzerConfigRepository())->findByScope(static::TEST_SOURCE_IDENTIFIER, static::TEST_STORE_NAME);

        $this->assertNotNull($result);
        $this->assertSame('light_german', $result->getStemmerLanguage());
        $this->assertCount(1, $result->getDecompoundWords());
        $this->assertSame('sofa', $result->getDecompoundWords()->offsetGet(0)->getTerm());
        $this->assertCount(1, $result->getSynonyms());
        $this->assertSame('couch, sofa', $result->getSynonyms()->offsetGet(0)->getTerm());
        $this->assertCount(0, $result->getStopwords());
        $this->assertCount(1, $result->getDoNotDecompoundTerms());
    }

    public function testFindByScopePreservesSortOrderWithinAListType(): void
    {
        $searchAnalyzerConfigTransfer = (new SearchAnalyzerConfigTransfer())
            ->setSourceIdentifier(static::TEST_SOURCE_IDENTIFIER)
            ->setStoreName(static::TEST_STORE_NAME)
            ->setDecompoundWords(new ArrayObject([$this->term('third'), $this->term('first'), $this->term('second')]));

        (new SearchAnalyzerConfigEntityManager())->saveSearchAnalyzerConfig(
            $searchAnalyzerConfigTransfer,
            SearchAnalyzerConfigConfig::CHANGE_SOURCE_MANUAL,
            null,
        );

        $result = (new SearchAnalyzerConfigRepository())->findByScope(static::TEST_SOURCE_IDENTIFIER, static::TEST_STORE_NAME);

        $terms = array_map(
            static fn (SearchAnalyzerConfigTermTransfer $searchAnalyzerConfigTermTransfer): ?string => $searchAnalyzerConfigTermTransfer->getTerm(),
            iterator_to_array($result->getDecompoundWords()),
        );

        $this->assertSame(['third', 'first', 'second'], $terms);
    }

    public function testGetRevisionHistoryReturnsEmptyArrayForAnUnstagedScope(): void
    {
        $result = (new SearchAnalyzerConfigRepository())->getRevisionHistory(static::TEST_SOURCE_IDENTIFIER, static::TEST_STORE_NAME);

        $this->assertSame([], $result);
    }

    public function testGetRevisionHistoryReturnsRevisionsNewestFirst(): void
    {
        $entityManager = new SearchAnalyzerConfigEntityManager();

        $entityManager->saveSearchAnalyzerConfig(
            (new SearchAnalyzerConfigTransfer())
                ->setSourceIdentifier(static::TEST_SOURCE_IDENTIFIER)
                ->setStoreName(static::TEST_STORE_NAME)
                ->setStemmerLanguage('light_german'),
            SearchAnalyzerConfigConfig::CHANGE_SOURCE_MANUAL,
            'first-user',
        );
        $entityManager->saveSearchAnalyzerConfig(
            (new SearchAnalyzerConfigTransfer())
                ->setSourceIdentifier(static::TEST_SOURCE_IDENTIFIER)
                ->setStoreName(static::TEST_STORE_NAME)
                ->setStemmerLanguage('minimal_english'),
            SearchAnalyzerConfigConfig::CHANGE_SOURCE_MANUAL,
            'second-user',
        );

        $result = (new SearchAnalyzerConfigRepository())->getRevisionHistory(static::TEST_SOURCE_IDENTIFIER, static::TEST_STORE_NAME);

        $this->assertCount(2, $result);
        $this->assertSame(2, $result[0]->getRevision());
        $this->assertSame('second-user', $result[0]->getTriggeredByUser());
        $this->assertSame(1, $result[1]->getRevision());
        $this->assertSame('first-user', $result[1]->getTriggeredByUser());
    }

    public function testGetRevisionHistoryDecodesTheFullSnapshotIncludingTermListsAndSortOrder(): void
    {
        (new SearchAnalyzerConfigEntityManager())->saveSearchAnalyzerConfig(
            (new SearchAnalyzerConfigTransfer())
                ->setSourceIdentifier(static::TEST_SOURCE_IDENTIFIER)
                ->setStoreName(static::TEST_STORE_NAME)
                ->setStemmerLanguage('light_german')
                ->setDecompoundEnabled(true)
                ->setDecompoundWords(new ArrayObject([$this->term('third'), $this->term('first'), $this->term('second')]))
                ->setSynonyms(new ArrayObject([$this->term('couch, sofa')]))
                ->setDoNotDecompoundTerms(new ArrayObject([$this->term('Brennenstuhl')])),
            SearchAnalyzerConfigConfig::CHANGE_SOURCE_MANUAL,
            null,
        );

        $result = (new SearchAnalyzerConfigRepository())->getRevisionHistory(static::TEST_SOURCE_IDENTIFIER, static::TEST_STORE_NAME);
        $snapshot = $result[0]->getSearchAnalyzerConfig();

        $this->assertSame('light_german', $snapshot->getStemmerLanguage());
        $this->assertTrue($snapshot->getDecompoundEnabled());
        $this->assertSame(
            ['third', 'first', 'second'],
            array_map(
                static fn (SearchAnalyzerConfigTermTransfer $searchAnalyzerConfigTermTransfer): ?string => $searchAnalyzerConfigTermTransfer->getTerm(),
                iterator_to_array($snapshot->getDecompoundWords()),
            ),
        );
        $this->assertCount(1, $snapshot->getSynonyms());
        $this->assertSame('couch, sofa', $snapshot->getSynonyms()->offsetGet(0)->getTerm());
        $this->assertCount(1, $snapshot->getDoNotDecompoundTerms());
        $this->assertSame('Brennenstuhl', $snapshot->getDoNotDecompoundTerms()->offsetGet(0)->getTerm());
    }

    public function testGetRevisionHistoryPopulatesRevisionBookkeepingFields(): void
    {
        (new SearchAnalyzerConfigEntityManager())->saveSearchAnalyzerConfig(
            (new SearchAnalyzerConfigTransfer())
                ->setSourceIdentifier(static::TEST_SOURCE_IDENTIFIER)
                ->setStoreName(static::TEST_STORE_NAME),
            SearchAnalyzerConfigConfig::CHANGE_SOURCE_RESTORE,
            'restoring-user',
        );

        $result = (new SearchAnalyzerConfigRepository())->getRevisionHistory(static::TEST_SOURCE_IDENTIFIER, static::TEST_STORE_NAME);

        $this->assertNotNull($result[0]->getIdSearchAnalyzerConfigRevision());
        $this->assertSame(SearchAnalyzerConfigConfig::CHANGE_SOURCE_RESTORE, $result[0]->getChangeSource());
        $this->assertSame('restoring-user', $result[0]->getTriggeredByUser());
        $this->assertNotNull($result[0]->getCreatedAt());
        $this->assertSame(static::TEST_SOURCE_IDENTIFIER, $result[0]->getSearchAnalyzerConfig()->getSourceIdentifier());
        $this->assertSame(static::TEST_STORE_NAME, $result[0]->getSearchAnalyzerConfig()->getStoreName());
    }

    /**
     * @param string $term
     */
    protected function term(string $term): SearchAnalyzerConfigTermTransfer
    {
        return (new SearchAnalyzerConfigTermTransfer())->setTerm($term);
    }
}
