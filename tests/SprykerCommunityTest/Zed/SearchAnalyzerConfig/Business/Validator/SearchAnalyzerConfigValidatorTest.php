<?php

/**
 * This file is part of the spryker-community/search-analyzer-config package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Zed\SearchAnalyzerConfig\Business\Validator;

use ArrayObject;
use Codeception\Test\Unit;
use Generated\Shared\Transfer\SearchAnalyzerConfigTermTransfer;
use Generated\Shared\Transfer\SearchAnalyzerConfigTransfer;
use SprykerCommunity\Shared\SearchAnalyzerConfig\SearchAnalyzerConfigConfig;
use SprykerCommunity\Zed\SearchAnalyzerConfig\Business\Validator\SearchAnalyzerConfigValidator;

/**
 * Auto-generated group annotations
 *
 * @group SprykerCommunityTest
 * @group Zed
 * @group SearchAnalyzerConfig
 * @group Business
 * @group Validator
 * @group SearchAnalyzerConfigValidatorTest
 * Add your own group annotations below this line
 * @group Portable
 */
class SearchAnalyzerConfigValidatorTest extends Unit
{
    public function testEmptyConfigIsValid(): void
    {
        $errors = (new SearchAnalyzerConfigValidator())->validate(new SearchAnalyzerConfigTransfer());

        $this->assertSame([], $errors);
    }

    public function testUnknownStopwordsModeIsRejected(): void
    {
        $searchAnalyzerConfigTransfer = (new SearchAnalyzerConfigTransfer())->setStopwordsMode('bogus');

        $errors = (new SearchAnalyzerConfigValidator())->validate($searchAnalyzerConfigTransfer);

        $this->assertNotEmpty($errors);
    }

    public function testBuiltinStopwordsModeWithoutLanguageIsRejected(): void
    {
        $searchAnalyzerConfigTransfer = (new SearchAnalyzerConfigTransfer())
            ->setStopwordsMode(SearchAnalyzerConfigConfig::STOPWORDS_MODE_BUILTIN);

        $errors = (new SearchAnalyzerConfigValidator())->validate($searchAnalyzerConfigTransfer);

        $this->assertNotEmpty($errors);
    }

    public function testBuiltinStopwordsModeWithLanguageIsValid(): void
    {
        $searchAnalyzerConfigTransfer = (new SearchAnalyzerConfigTransfer())
            ->setStopwordsMode(SearchAnalyzerConfigConfig::STOPWORDS_MODE_BUILTIN)
            ->setStopwordsBuiltinLanguage('_german_');

        $errors = (new SearchAnalyzerConfigValidator())->validate($searchAnalyzerConfigTransfer);

        $this->assertSame([], $errors);
    }

    public function testDoNotDecompoundTermWithDisallowedCharactersIsRejected(): void
    {
        $searchAnalyzerConfigTransfer = (new SearchAnalyzerConfigTransfer())
            ->setDoNotDecompoundTerms(new ArrayObject([$this->term('brennen$tuhl')]));

        $errors = (new SearchAnalyzerConfigValidator())->validate($searchAnalyzerConfigTransfer);

        $this->assertNotEmpty($errors);
    }

    public function testDoNotDecompoundTermWithAllowedCharactersIsValid(): void
    {
        $searchAnalyzerConfigTransfer = (new SearchAnalyzerConfigTransfer())
            ->setDoNotDecompoundTerms(new ArrayObject([$this->term('Brennenstuhl'), $this->term('Contorion-24.7')]));

        $errors = (new SearchAnalyzerConfigValidator())->validate($searchAnalyzerConfigTransfer);

        $this->assertSame([], $errors);
    }

    public function testDecompoundEnabledWithEmptyWordListIsRejected(): void
    {
        $searchAnalyzerConfigTransfer = (new SearchAnalyzerConfigTransfer())
            ->setDecompoundEnabled(true);

        $errors = (new SearchAnalyzerConfigValidator())->validate($searchAnalyzerConfigTransfer);

        $this->assertNotEmpty($errors);
    }

    public function testDecompoundEnabledWithBlankOnlyWordsIsRejected(): void
    {
        $searchAnalyzerConfigTransfer = (new SearchAnalyzerConfigTransfer())
            ->setDecompoundEnabled(true)
            ->setDecompoundWords(new ArrayObject([$this->term('   ')]));

        $errors = (new SearchAnalyzerConfigValidator())->validate($searchAnalyzerConfigTransfer);

        $this->assertNotEmpty($errors);
    }

    public function testDecompoundDisabledWithEmptyWordListIsValid(): void
    {
        $searchAnalyzerConfigTransfer = (new SearchAnalyzerConfigTransfer())
            ->setDecompoundEnabled(false);

        $errors = (new SearchAnalyzerConfigValidator())->validate($searchAnalyzerConfigTransfer);

        $this->assertSame([], $errors);
    }

    public function testStopwordsListWithABlankTermIsRejected(): void
    {
        $searchAnalyzerConfigTransfer = (new SearchAnalyzerConfigTransfer())
            ->setStopwordsMode(SearchAnalyzerConfigConfig::STOPWORDS_MODE_CUSTOM)
            ->setStopwords(new ArrayObject([$this->term('rain'), $this->term('   ')]));

        $errors = (new SearchAnalyzerConfigValidator())->validate($searchAnalyzerConfigTransfer);

        $this->assertNotEmpty($errors);
    }

    public function testSynonymsListWithABlankTermIsRejected(): void
    {
        $searchAnalyzerConfigTransfer = (new SearchAnalyzerConfigTransfer())
            ->setSynonyms(new ArrayObject([$this->term('sofa'), $this->term('')]));

        $errors = (new SearchAnalyzerConfigValidator())->validate($searchAnalyzerConfigTransfer);

        $this->assertNotEmpty($errors);
    }

    public function testDoNotDecompoundTermsWithABlankTermIsRejected(): void
    {
        $searchAnalyzerConfigTransfer = (new SearchAnalyzerConfigTransfer())
            ->setDoNotDecompoundTerms(new ArrayObject([$this->term('Brennenstuhl'), $this->term('  ')]));

        $errors = (new SearchAnalyzerConfigValidator())->validate($searchAnalyzerConfigTransfer);

        $this->assertNotEmpty($errors);
    }

    public function testDecompoundWordsListWithABlankTermAmongRealOnesIsRejected(): void
    {
        $searchAnalyzerConfigTransfer = (new SearchAnalyzerConfigTransfer())
            ->setDecompoundEnabled(true)
            ->setDecompoundWords(new ArrayObject([$this->term('kabel'), $this->term('')]));

        $errors = (new SearchAnalyzerConfigValidator())->validate($searchAnalyzerConfigTransfer);

        $this->assertNotEmpty($errors);
    }

    public function testOnlyNonBlankTermsAcrossAllListsIsValid(): void
    {
        $searchAnalyzerConfigTransfer = (new SearchAnalyzerConfigTransfer())
            ->setStopwordsMode(SearchAnalyzerConfigConfig::STOPWORDS_MODE_CUSTOM)
            ->setStopwords(new ArrayObject([$this->term('rain')]))
            ->setSynonyms(new ArrayObject([$this->term('sofa')]))
            ->setDoNotDecompoundTerms(new ArrayObject([$this->term('Brennenstuhl')]))
            ->setDecompoundEnabled(true)
            ->setDecompoundWords(new ArrayObject([$this->term('kabel')]));

        $errors = (new SearchAnalyzerConfigValidator())->validate($searchAnalyzerConfigTransfer);

        $this->assertSame([], $errors);
    }

    /**
     * @param string $term
     */
    protected function term(string $term): SearchAnalyzerConfigTermTransfer
    {
        return (new SearchAnalyzerConfigTermTransfer())->setTerm($term);
    }
}
