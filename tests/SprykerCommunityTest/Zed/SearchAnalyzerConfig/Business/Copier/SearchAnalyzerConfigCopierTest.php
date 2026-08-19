<?php

/**
 * This file is part of the spryker-community/search-analyzer-config package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Zed\SearchAnalyzerConfig\Business\Copier;

use ArrayObject;
use Codeception\Test\Unit;
use Generated\Shared\Transfer\SearchAnalyzerConfigTermTransfer;
use Generated\Shared\Transfer\SearchAnalyzerConfigTransfer;
use SprykerCommunity\Zed\SearchAnalyzerConfig\Business\Copier\SearchAnalyzerConfigCopier;

/**
 * Auto-generated group annotations
 *
 * @group SprykerCommunityTest
 * @group Zed
 * @group SearchAnalyzerConfig
 * @group Business
 * @group Copier
 * @group SearchAnalyzerConfigCopierTest
 * Add your own group annotations below this line
 * @group Portable
 */
class SearchAnalyzerConfigCopierTest extends Unit
{
    public function testCopyTargetsTheGivenScopeNotTheSourceScope(): void
    {
        $source = (new SearchAnalyzerConfigTransfer())
            ->setIdSearchAnalyzerConfig(42)
            ->setSourceIdentifier('page')
            ->setStoreName('DE');

        $copy = (new SearchAnalyzerConfigCopier())->copy($source, 'page', 'AT');

        $this->assertSame('page', $copy->getSourceIdentifier());
        $this->assertSame('AT', $copy->getStoreName());
    }

    public function testCopyDoesNotCarryOverIdentityOrRevisionBookkeeping(): void
    {
        $source = (new SearchAnalyzerConfigTransfer())
            ->setIdSearchAnalyzerConfig(42)
            ->setRevision(7)
            ->setAppliedRevision(6)
            ->setAppliedIndexName('de_page_20260101_000000')
            ->setAppliedAt('2026-01-01 00:00:00');

        $copy = (new SearchAnalyzerConfigCopier())->copy($source, 'page', 'AT');

        $this->assertNull($copy->getIdSearchAnalyzerConfig());
        $this->assertNull($copy->getRevision());
        $this->assertNull($copy->getAppliedRevision());
        $this->assertNull($copy->getAppliedIndexName());
        $this->assertNull($copy->getAppliedAt());
    }

    public function testCopyCarriesOverAllFiveEditableFieldsVerbatim(): void
    {
        $source = (new SearchAnalyzerConfigTransfer())
            ->setStemmerLanguage('light_german')
            ->setNormalizationFilter('german_normalization')
            ->setStopwordsMode('custom')
            ->setStopwordsBuiltinLanguage('_german_')
            ->setDecompoundEnabled(true);

        $copy = (new SearchAnalyzerConfigCopier())->copy($source, 'page', 'AT');

        $this->assertSame('light_german', $copy->getStemmerLanguage());
        $this->assertSame('german_normalization', $copy->getNormalizationFilter());
        $this->assertSame('custom', $copy->getStopwordsMode());
        $this->assertSame('_german_', $copy->getStopwordsBuiltinLanguage());
        $this->assertTrue($copy->getDecompoundEnabled());
    }

    public function testCopyDuplicatesEveryTermListButStripsEachTermsOwnId(): void
    {
        $source = (new SearchAnalyzerConfigTransfer())
            ->setDecompoundWords(new ArrayObject([$this->term(1, 'brenn'), $this->term(2, 'stuhl')]))
            ->setDoNotDecompoundTerms(new ArrayObject([$this->term(3, 'Brennenstuhl')]));

        $copy = (new SearchAnalyzerConfigCopier())->copy($source, 'page', 'AT');

        $this->assertCount(2, $copy->getDecompoundWords());
        $this->assertSame('brenn', $copy->getDecompoundWords()[0]->getTerm());
        $this->assertNull($copy->getDecompoundWords()[0]->getIdSearchAnalyzerConfigTerm());
        $this->assertCount(1, $copy->getDoNotDecompoundTerms());
        $this->assertSame('Brennenstuhl', $copy->getDoNotDecompoundTerms()[0]->getTerm());
    }

    /**
     * @param int $id
     * @param string $term
     */
    protected function term(int $id, string $term): SearchAnalyzerConfigTermTransfer
    {
        return (new SearchAnalyzerConfigTermTransfer())
            ->setIdSearchAnalyzerConfigTerm($id)
            ->setTerm($term);
    }
}
