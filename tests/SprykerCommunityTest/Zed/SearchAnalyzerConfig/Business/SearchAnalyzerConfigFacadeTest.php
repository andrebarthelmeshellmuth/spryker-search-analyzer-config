<?php

/**
 * This file is part of the spryker-community/search-analyzer-config package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Zed\SearchAnalyzerConfig\Business;

use ArrayObject;
use Codeception\Test\Unit;
use Generated\Shared\Transfer\SearchAnalyzerConfigTermTransfer;
use Generated\Shared\Transfer\SearchAnalyzerConfigTransfer;
use Orm\Zed\SearchAnalyzerConfig\Persistence\SpySearchAnalyzerConfigQuery;
use SprykerCommunity\Shared\SearchAnalyzerConfig\SearchAnalyzerConfigConfig;
use SprykerCommunity\Zed\SearchAnalyzerConfig\Business\SearchAnalyzerConfigFacade;
use SprykerCommunity\Zed\SearchAnalyzerConfig\Persistence\SearchAnalyzerConfigEntityManager;

/**
 * INTEGRATION TEST — real database via SearchAnalyzerConfigFacade's own EntityManager/Repository, never
 * mocked: the behavior worth protecting here is save()'s validate-before-persist gate and copy()'s
 * end-to-end scope-to-scope duplication, neither of which a mocked repository/entity-manager pair could
 * confirm.
 *
 * Auto-generated group annotations
 *
 * @group SprykerCommunityTest
 * @group Zed
 * @group SearchAnalyzerConfig
 * @group Business
 * @group SearchAnalyzerConfigFacadeTest
 * Add your own group annotations below this line
 * @group NeedsDatabase
 */
class SearchAnalyzerConfigFacadeTest extends Unit
{
    /**
     * @var string
     */
    protected const TEST_SOURCE_IDENTIFIER = 'phpunit_source';

    /**
     * @var string
     */
    protected const TEST_TARGET_SOURCE_IDENTIFIER = 'phpunit_target_source';

    /**
     * @var string
     */
    protected const TEST_STORE_NAME = 'PHPUNIT';

    /**
     * @var string
     */
    protected const TEST_TARGET_STORE_NAME = 'PHPUNIT_TARGET';

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
        foreach ([static::TEST_SOURCE_IDENTIFIER, static::TEST_TARGET_SOURCE_IDENTIFIER] as $sourceIdentifier) {
            SpySearchAnalyzerConfigQuery::create()
                ->filterBySourceIdentifier($sourceIdentifier)
                ->delete();
        }
    }

    public function testSaveWithInvalidConfigReturnsErrorsAndDoesNotPersist(): void
    {
        $searchAnalyzerConfigTransfer = (new SearchAnalyzerConfigTransfer())
            ->setSourceIdentifier(static::TEST_SOURCE_IDENTIFIER)
            ->setStoreName(static::TEST_STORE_NAME)
            ->setStopwordsMode(SearchAnalyzerConfigConfig::STOPWORDS_MODE_BUILTIN);

        $errors = $this->createFacade()->save($searchAnalyzerConfigTransfer, SearchAnalyzerConfigConfig::CHANGE_SOURCE_MANUAL, null);

        $this->assertNotEmpty($errors);
        $this->assertNull($this->createFacade()->findByScope(static::TEST_SOURCE_IDENTIFIER, static::TEST_STORE_NAME));
    }

    public function testSaveWithValidConfigPersistsAndIsFindable(): void
    {
        $searchAnalyzerConfigTransfer = (new SearchAnalyzerConfigTransfer())
            ->setSourceIdentifier(static::TEST_SOURCE_IDENTIFIER)
            ->setStoreName(static::TEST_STORE_NAME)
            ->setStemmerLanguage('light_german');

        $errors = $this->createFacade()->save($searchAnalyzerConfigTransfer, SearchAnalyzerConfigConfig::CHANGE_SOURCE_MANUAL, null);

        $this->assertSame([], $errors);

        $found = $this->createFacade()->findByScope(static::TEST_SOURCE_IDENTIFIER, static::TEST_STORE_NAME);
        $this->assertNotNull($found);
        $this->assertSame('light_german', $found->getStemmerLanguage());
    }

    public function testCopyFromAnUnstagedScopeReturnsAnErrorAndPersistsNothing(): void
    {
        $errors = $this->createFacade()->copy(
            static::TEST_SOURCE_IDENTIFIER,
            static::TEST_TARGET_SOURCE_IDENTIFIER,
            static::TEST_STORE_NAME,
            static::TEST_TARGET_STORE_NAME,
            null,
        );

        $this->assertNotEmpty($errors);
        $this->assertNull($this->createFacade()->findByScope(static::TEST_TARGET_SOURCE_IDENTIFIER, static::TEST_TARGET_STORE_NAME));
    }

    public function testCopyDuplicatesTheSourceScopeIntoTheTargetScope(): void
    {
        $facade = $this->createFacade();

        $sourceTransfer = (new SearchAnalyzerConfigTransfer())
            ->setSourceIdentifier(static::TEST_SOURCE_IDENTIFIER)
            ->setStoreName(static::TEST_STORE_NAME)
            ->setStemmerLanguage('light_german')
            ->setDecompoundEnabled(true)
            ->setDecompoundWords(new ArrayObject([$this->term('sofa')]));

        $facade->save($sourceTransfer, SearchAnalyzerConfigConfig::CHANGE_SOURCE_MANUAL, null);

        $errors = $facade->copy(
            static::TEST_SOURCE_IDENTIFIER,
            static::TEST_TARGET_SOURCE_IDENTIFIER,
            static::TEST_STORE_NAME,
            static::TEST_TARGET_STORE_NAME,
            'phpunit-user',
        );

        $this->assertSame([], $errors);

        $target = $facade->findByScope(static::TEST_TARGET_SOURCE_IDENTIFIER, static::TEST_TARGET_STORE_NAME);
        $this->assertNotNull($target);
        $this->assertSame('light_german', $target->getStemmerLanguage());
        $this->assertCount(1, $target->getDecompoundWords());

        // The source scope is untouched by the copy.
        $source = $facade->findByScope(static::TEST_SOURCE_IDENTIFIER, static::TEST_STORE_NAME);
        $this->assertNotNull($source);
        $this->assertSame(1, $source->getRevision());
    }

    public function testCopyValidatesTheCopiedConfigBeforePersistingRatherThanDuplicatingItBlind(): void
    {
        // Written directly via the EntityManager, bypassing Facade::save()'s own validation gate -- the
        // realistic way an invalid term ends up persisted in the first place (e.g. it predates a
        // TERM_PATTERN tightening).
        (new SearchAnalyzerConfigEntityManager())->saveSearchAnalyzerConfig(
            (new SearchAnalyzerConfigTransfer())
                ->setSourceIdentifier(static::TEST_SOURCE_IDENTIFIER)
                ->setStoreName(static::TEST_STORE_NAME)
                ->setDoNotDecompoundTerms(new ArrayObject([$this->term('not valid; term')])),
            SearchAnalyzerConfigConfig::CHANGE_SOURCE_MANUAL,
            null,
        );

        $errors = $this->createFacade()->copy(
            static::TEST_SOURCE_IDENTIFIER,
            static::TEST_TARGET_SOURCE_IDENTIFIER,
            static::TEST_STORE_NAME,
            static::TEST_TARGET_STORE_NAME,
            null,
        );

        $this->assertNotEmpty($errors);
        $this->assertNull($this->createFacade()->findByScope(static::TEST_TARGET_SOURCE_IDENTIFIER, static::TEST_TARGET_STORE_NAME));
    }

    public function testRenderIntoSettingsPassesThroughUnchangedForAnUnstagedScope(): void
    {
        $baseSettings = ['analysis' => ['analyzer' => []]];

        $result = $this->createFacade()->renderIntoSettings(static::TEST_SOURCE_IDENTIFIER, static::TEST_STORE_NAME, $baseSettings);

        $this->assertSame($baseSettings, $result);
    }

    public function testMarkAppliedDelegatesToTheEntityManager(): void
    {
        $facade = $this->createFacade();
        $facade->save(
            (new SearchAnalyzerConfigTransfer())
                ->setSourceIdentifier(static::TEST_SOURCE_IDENTIFIER)
                ->setStoreName(static::TEST_STORE_NAME),
            SearchAnalyzerConfigConfig::CHANGE_SOURCE_MANUAL,
            null,
        );

        $facade->markApplied(static::TEST_SOURCE_IDENTIFIER, static::TEST_STORE_NAME, 1, 'phpunit_page_index');

        $found = $facade->findByScope(static::TEST_SOURCE_IDENTIFIER, static::TEST_STORE_NAME);
        $this->assertSame(1, $found->getAppliedRevision());
        $this->assertSame('phpunit_page_index', $found->getAppliedIndexName());
    }

    /**
     * @return \SprykerCommunity\Zed\SearchAnalyzerConfig\Business\SearchAnalyzerConfigFacadeInterface
     */
    protected function createFacade(): SearchAnalyzerConfigFacade
    {
        return new SearchAnalyzerConfigFacade();
    }

    /**
     * @param string $term
     */
    protected function term(string $term): SearchAnalyzerConfigTermTransfer
    {
        return (new SearchAnalyzerConfigTermTransfer())->setTerm($term);
    }
}
