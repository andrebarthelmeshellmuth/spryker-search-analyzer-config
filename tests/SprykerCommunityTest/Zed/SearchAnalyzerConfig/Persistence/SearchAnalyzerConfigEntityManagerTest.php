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
use Orm\Zed\SearchAnalyzerConfig\Persistence\SpySearchAnalyzerConfigRevisionQuery;
use Orm\Zed\SearchAnalyzerConfig\Persistence\SpySearchAnalyzerConfigTermQuery;
use SprykerCommunity\Shared\SearchAnalyzerConfig\SearchAnalyzerConfigConfig;
use SprykerCommunity\Zed\SearchAnalyzerConfig\Persistence\SearchAnalyzerConfigEntityManager;

/**
 * INTEGRATION TEST — real database, real rows, never mocked: the revision-increment and full-replace
 * term-list behavior are exactly what a mocked query builder could not confirm.
 *
 * Auto-generated group annotations
 *
 * @group SprykerCommunityTest
 * @group Zed
 * @group SearchAnalyzerConfig
 * @group Persistence
 * @group SearchAnalyzerConfigEntityManagerTest
 * Add your own group annotations below this line
 * @group NeedsDatabase
 */
class SearchAnalyzerConfigEntityManagerTest extends Unit
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
        $spySearchAnalyzerConfig = SpySearchAnalyzerConfigQuery::create()
            ->filterBySourceIdentifier(static::TEST_SOURCE_IDENTIFIER)
            ->filterByStoreName(static::TEST_STORE_NAME)
            ->findOne();

        if ($spySearchAnalyzerConfig === null) {
            return;
        }

        SpySearchAnalyzerConfigTermQuery::create()
            ->filterByFkSearchAnalyzerConfig($spySearchAnalyzerConfig->getIdSearchAnalyzerConfig())
            ->delete();

        SpySearchAnalyzerConfigRevisionQuery::create()
            ->filterByFkSearchAnalyzerConfig($spySearchAnalyzerConfig->getIdSearchAnalyzerConfig())
            ->delete();

        $spySearchAnalyzerConfig->delete();
    }

    public function testSaveSearchAnalyzerConfigPersistsAndAssignsRevisionOne(): void
    {
        $searchAnalyzerConfigTransfer = $this->createSearchAnalyzerConfigTransfer();

        $result = (new SearchAnalyzerConfigEntityManager())->saveSearchAnalyzerConfig(
            $searchAnalyzerConfigTransfer,
            SearchAnalyzerConfigConfig::CHANGE_SOURCE_MANUAL,
            'phpunit-user',
        );

        $this->assertSame(1, $result->getRevision());
        $this->assertNotNull($result->getIdSearchAnalyzerConfig());

        $entity = SpySearchAnalyzerConfigQuery::create()->findOneByIdSearchAnalyzerConfig($result->getIdSearchAnalyzerConfigOrFail());
        $this->assertNotNull($entity);
        $this->assertSame('light_german', $entity->getStemmerLanguage());
        $this->assertTrue($entity->getDecompoundEnabled());
    }

    public function testSavingTwiceIncrementsRevision(): void
    {
        $entityManager = new SearchAnalyzerConfigEntityManager();

        $first = $entityManager->saveSearchAnalyzerConfig(
            $this->createSearchAnalyzerConfigTransfer(),
            SearchAnalyzerConfigConfig::CHANGE_SOURCE_MANUAL,
            null,
        );

        $second = $entityManager->saveSearchAnalyzerConfig(
            $this->createSearchAnalyzerConfigTransfer(),
            SearchAnalyzerConfigConfig::CHANGE_SOURCE_MANUAL,
            null,
        );

        $this->assertSame(1, $first->getRevision());
        $this->assertSame(2, $second->getRevision());
        $this->assertSame($first->getIdSearchAnalyzerConfig(), $second->getIdSearchAnalyzerConfig());
    }

    public function testSavingReplacesTermsRatherThanAppending(): void
    {
        $entityManager = new SearchAnalyzerConfigEntityManager();

        $firstTransfer = $this->createSearchAnalyzerConfigTransfer();
        $firstTransfer->setDecompoundWords(new ArrayObject([$this->term('foo'), $this->term('bar')]));
        $entityManager->saveSearchAnalyzerConfig($firstTransfer, SearchAnalyzerConfigConfig::CHANGE_SOURCE_MANUAL, null);

        $secondTransfer = $this->createSearchAnalyzerConfigTransfer();
        $secondTransfer->setDecompoundWords(new ArrayObject([$this->term('baz')]));
        $result = $entityManager->saveSearchAnalyzerConfig($secondTransfer, SearchAnalyzerConfigConfig::CHANGE_SOURCE_MANUAL, null);

        $remainingTerms = SpySearchAnalyzerConfigTermQuery::create()
            ->filterByFkSearchAnalyzerConfig($result->getIdSearchAnalyzerConfigOrFail())
            ->find();

        $this->assertCount(1, $remainingTerms);
        $this->assertSame('baz', $remainingTerms->getFirst()->getTerm());
    }

    public function testSavingWritesARevisionSnapshot(): void
    {
        $result = (new SearchAnalyzerConfigEntityManager())->saveSearchAnalyzerConfig(
            $this->createSearchAnalyzerConfigTransfer(),
            SearchAnalyzerConfigConfig::CHANGE_SOURCE_MANUAL,
            'phpunit-user',
        );

        $revision = SpySearchAnalyzerConfigRevisionQuery::create()
            ->filterByFkSearchAnalyzerConfig($result->getIdSearchAnalyzerConfigOrFail())
            ->findOne();

        $this->assertNotNull($revision);
        $this->assertSame(1, $revision->getRevision());
        $this->assertSame(SearchAnalyzerConfigConfig::CHANGE_SOURCE_MANUAL, $revision->getChangeSource());
        $this->assertSame('phpunit-user', $revision->getTriggeredByUser());

        $snapshot = json_decode((string)$revision->getSnapshot(), true);
        $this->assertSame('light_german', $snapshot['stemmerLanguage']);
    }

    public function testMarkAppliedUpdatesTheApplyColumns(): void
    {
        $entityManager = new SearchAnalyzerConfigEntityManager();
        $result = $entityManager->saveSearchAnalyzerConfig(
            $this->createSearchAnalyzerConfigTransfer(),
            SearchAnalyzerConfigConfig::CHANGE_SOURCE_MANUAL,
            null,
        );

        $entityManager->markApplied(static::TEST_SOURCE_IDENTIFIER, static::TEST_STORE_NAME, 1, 'phpunit_page_20260801');

        $entity = SpySearchAnalyzerConfigQuery::create()->findOneByIdSearchAnalyzerConfig($result->getIdSearchAnalyzerConfigOrFail());
        $this->assertSame(1, $entity->getAppliedRevision());
        $this->assertSame('phpunit_page_20260801', $entity->getAppliedIndexName());
        $this->assertNotNull($entity->getAppliedAt());
    }

    public function testMarkAppliedOnUnknownScopeIsANoop(): void
    {
        $entityManager = new SearchAnalyzerConfigEntityManager();

        // Asserts only that this does not throw — the interesting behavior is the early return.
        $entityManager->markApplied('does-not-exist', 'NOPE', 1, 'irrelevant');

        $this->assertNull(SpySearchAnalyzerConfigQuery::create()->filterBySourceIdentifier('does-not-exist')->findOne());
    }

    protected function createSearchAnalyzerConfigTransfer(): SearchAnalyzerConfigTransfer
    {
        return (new SearchAnalyzerConfigTransfer())
            ->setSourceIdentifier(static::TEST_SOURCE_IDENTIFIER)
            ->setStoreName(static::TEST_STORE_NAME)
            ->setStemmerLanguage('light_german')
            ->setDecompoundEnabled(true)
            ->setDecompoundWords(new ArrayObject([$this->term('sofa')]));
    }

    /**
     * @param string $term
     */
    protected function term(string $term): SearchAnalyzerConfigTermTransfer
    {
        return (new SearchAnalyzerConfigTermTransfer())->setTerm($term);
    }
}
