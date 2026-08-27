<?php

/**
 * This file is part of the spryker-community/search-analyzer-config package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Zed\SearchAnalyzerConfig\Business\Renderer;

use ArrayObject;
use Codeception\Test\Unit;
use Generated\Shared\Transfer\SearchAnalyzerConfigTermTransfer;
use Generated\Shared\Transfer\SearchAnalyzerConfigTransfer;
use SprykerCommunity\Shared\SearchAnalyzerConfig\SearchAnalyzerConfigConfig;
use SprykerCommunity\Zed\SearchAnalyzerConfig\Business\Exception\SearchAnalyzerConfigInvalidTermException;
use SprykerCommunity\Zed\SearchAnalyzerConfig\Business\Renderer\SearchAnalyzerConfigRenderer;

/**
 * Auto-generated group annotations
 *
 * @group SprykerCommunityTest
 * @group Zed
 * @group SearchAnalyzerConfig
 * @group Business
 * @group Renderer
 * @group SearchAnalyzerConfigRendererTest
 * Add your own group annotations below this line
 * @group Portable
 */
class SearchAnalyzerConfigRendererTest extends Unit
{
    /**
     * @var array<string, mixed>
     */
    protected const FULLY_SLOTTED_BASE_SETTINGS = [
        'analysis' => [
            'analyzer' => [
                'fulltext_search_analyzer' => [
                    'tokenizer' => 'standard',
                    'filter' => [
                        'lowercase',
                        'sac_normalization',
                        'sac_decompound',
                        'sac_synonyms',
                        'sac_stopwords',
                        'sac_keyword_marker',
                        'sac_stemmer',
                    ],
                ],
            ],
        ],
    ];

    public function testResolveTargetAnalyzerNamesDiscoversAnAnalyzerReferencingAtLeastOneSlot(): void
    {
        $this->assertSame(
            ['fulltext_search_analyzer'],
            (new SearchAnalyzerConfigRenderer())->resolveTargetAnalyzerNames(static::FULLY_SLOTTED_BASE_SETTINGS),
        );
    }

    /**
     * An analyzer whose own chain references none of this package's well-known slot names is not a
     * target -- nobody names a filter "sac_stemmer" by accident, so the absence of any such name is a real
     * signal, not something worth guessing about.
     */
    public function testResolveTargetAnalyzerNamesExcludesAnAnalyzerReferencingNoKnownSlot(): void
    {
        $baseSettings = [
            'analysis' => [
                'analyzer' => [
                    'unrelated_analyzer' => [
                        'tokenizer' => 'standard',
                        'filter' => ['lowercase', 'some_other_filter'],
                    ],
                ],
            ],
        ];

        $this->assertSame([], (new SearchAnalyzerConfigRenderer())->resolveTargetAnalyzerNames($baseSettings));
    }

    /**
     * Greenfield case: no live index yet (or a $baseSettings shape that carries no analyzer map at all)
     * resolves to an empty target list, not an error.
     */
    public function testResolveTargetAnalyzerNamesReturnsEmptyForEmptyBaseSettings(): void
    {
        $this->assertSame([], (new SearchAnalyzerConfigRenderer())->resolveTargetAnalyzerNames([]));
    }

    public function testDescribeSlotAvailabilityReportsEveryChainVisibleSlotPerAnalyzer(): void
    {
        $baseSettings = static::FULLY_SLOTTED_BASE_SETTINGS;

        $availability = (new SearchAnalyzerConfigRenderer())->describeSlotAvailability($baseSettings);

        $this->assertSame(
            SearchAnalyzerConfigRenderer::CHAIN_VISIBLE_SLOT_NAMES_IN_RECOMMENDED_ORDER,
            array_keys($availability),
        );
        $this->assertTrue($availability['sac_synonyms']['fulltext_search_analyzer']);
        $this->assertTrue($availability['sac_decompound']['fulltext_search_analyzer']);
    }

    public function testDescribeSlotAvailabilityReportsFalseForAnUnreferencedSlot(): void
    {
        $baseSettings = [
            'analysis' => [
                'analyzer' => [
                    'fulltext_search_analyzer' => [
                        'tokenizer' => 'standard',
                        'filter' => ['lowercase', 'sac_synonyms'],
                    ],
                ],
            ],
        ];

        $availability = (new SearchAnalyzerConfigRenderer())->describeSlotAvailability($baseSettings);

        $this->assertTrue($availability['sac_synonyms']['fulltext_search_analyzer']);
        $this->assertFalse($availability['sac_decompound']['fulltext_search_analyzer']);
    }

    public function testDescribeSlotAvailabilityDiffersPerAnalyzerAndNeverThrows(): void
    {
        $baseSettings = [
            'analysis' => [
                'analyzer' => [
                    'fulltext_index_analyzer' => [
                        'tokenizer' => 'standard',
                        'filter' => ['lowercase', 'sac_decompound'],
                    ],
                    'fulltext_search_analyzer' => [
                        'tokenizer' => 'standard',
                        'filter' => ['lowercase', 'sac_synonyms'],
                    ],
                ],
            ],
        ];

        $availability = (new SearchAnalyzerConfigRenderer())->describeSlotAvailability($baseSettings);

        $this->assertTrue($availability['sac_decompound']['fulltext_index_analyzer']);
        $this->assertFalse($availability['sac_decompound']['fulltext_search_analyzer']);
    }

    /**
     * An analyzer referencing no known slot at all isn't discovered, so it never appears in the
     * per-slot availability map either -- describeSlotAvailability() simply has nothing to report for it.
     */
    public function testDescribeSlotAvailabilityOmitsAnAnalyzerReferencingNoKnownSlot(): void
    {
        $availability = (new SearchAnalyzerConfigRenderer())->describeSlotAvailability([]);

        foreach (SearchAnalyzerConfigRenderer::CHAIN_VISIBLE_SLOT_NAMES_IN_RECOMMENDED_ORDER as $slotName) {
            $this->assertSame([], $availability[$slotName]);
        }
    }

    public function testEmptyConfigReturnsBaseSettingsUnchanged(): void
    {
        $searchAnalyzerConfigTransfer = new SearchAnalyzerConfigTransfer();
        $baseSettings = ['number_of_shards' => '1'];

        $result = (new SearchAnalyzerConfigRenderer())->render($searchAnalyzerConfigTransfer, $baseSettings);

        $this->assertSame($baseSettings, $result);
    }

    public function testEmptyBaseSettingsAreReturnedUnchangedEvenWhenAFieldIsActive(): void
    {
        $searchAnalyzerConfigTransfer = (new SearchAnalyzerConfigTransfer())
            ->setStemmerLanguage('light_german');

        $result = (new SearchAnalyzerConfigRenderer())->render($searchAnalyzerConfigTransfer, []);

        $this->assertSame([], $result);
    }

    public function testNeverAddsAFilterNameToTheAnalyzersOwnFilterChainOrChangesItsOrder(): void
    {
        $searchAnalyzerConfigTransfer = (new SearchAnalyzerConfigTransfer())
            ->setStemmerLanguage('light_german');

        $baseSettings = static::FULLY_SLOTTED_BASE_SETTINGS;
        $originalChain = $baseSettings['analysis']['analyzer']['fulltext_search_analyzer']['filter'];

        $result = (new SearchAnalyzerConfigRenderer())->render($searchAnalyzerConfigTransfer, $baseSettings);

        $this->assertSame($originalChain, $result['analysis']['analyzer']['fulltext_search_analyzer']['filter']);
    }

    public function testStemmerLanguageOverwritesDataInAnAlreadyReferencedSlot(): void
    {
        $searchAnalyzerConfigTransfer = (new SearchAnalyzerConfigTransfer())
            ->setStemmerLanguage('light_german');

        $result = (new SearchAnalyzerConfigRenderer())->render($searchAnalyzerConfigTransfer, static::FULLY_SLOTTED_BASE_SETTINGS);

        $this->assertSame(
            ['type' => 'stemmer', 'language' => 'light_german'],
            $result['analysis']['filter']['sac_stemmer'],
        );
    }

    public function testStemmerLanguageUnsetLeavesAnExistingSlotBodyUntouched(): void
    {
        $baseSettings = static::FULLY_SLOTTED_BASE_SETTINGS;
        $baseSettings['analysis']['filter']['sac_stemmer'] = ['type' => 'stemmer', 'language' => 'project_default'];

        $result = (new SearchAnalyzerConfigRenderer())->render(new SearchAnalyzerConfigTransfer(), $baseSettings);

        $this->assertSame(
            ['type' => 'stemmer', 'language' => 'project_default'],
            $result['analysis']['filter']['sac_stemmer'],
        );
    }

    /**
     * A missing slot on an analyzer that DOES exist is a deliberate per-analyzer opt-out now, not an
     * error -- render() simply skips that field for that analyzer. See
     * testActiveStemmerLanguageWithoutAReferencedSlotWarnsButDoesNotThrow() for the same case surfaced as
     * a non-fatal warning via collectMissingSlotWarnings(). The chain still needs at least ONE unrelated
     * known slot (sac_synonyms here) so the analyzer is auto-discovered as a target at all.
     */
    public function testActiveStemmerLanguageWithoutAReferencedSlotIsSkippedWithoutThrowing(): void
    {
        $searchAnalyzerConfigTransfer = (new SearchAnalyzerConfigTransfer())
            ->setStemmerLanguage('light_german');

        $baseSettings = [
            'analysis' => [
                'analyzer' => [
                    'fulltext_search_analyzer' => [
                        'tokenizer' => 'standard',
                        'filter' => ['lowercase', 'sac_synonyms'],
                    ],
                ],
            ],
        ];

        $result = (new SearchAnalyzerConfigRenderer())->render($searchAnalyzerConfigTransfer, $baseSettings);

        $this->assertArrayNotHasKey('sac_stemmer', $result['analysis']['filter'] ?? []);
    }

    public function testActiveStemmerLanguageWithoutAReferencedSlotWarnsButDoesNotThrow(): void
    {
        $searchAnalyzerConfigTransfer = (new SearchAnalyzerConfigTransfer())
            ->setStemmerLanguage('light_german');

        $baseSettings = [
            'analysis' => [
                'analyzer' => [
                    'fulltext_search_analyzer' => [
                        'tokenizer' => 'standard',
                        'filter' => ['lowercase', 'sac_synonyms'],
                    ],
                ],
            ],
        ];

        $warnings = (new SearchAnalyzerConfigRenderer())->collectMissingSlotWarnings($searchAnalyzerConfigTransfer, $baseSettings);

        $this->assertCount(1, $warnings);
        $this->assertStringContainsString('sac_stemmer', $warnings[0]);
        // Must not have mutated the caller's settings.
        $this->assertArrayNotHasKey('sac_stemmer', $baseSettings['analysis']['filter'] ?? []);
    }

    public function testCollectMissingSlotWarningsCollectsOneMessagePerAffectedField(): void
    {
        $searchAnalyzerConfigTransfer = (new SearchAnalyzerConfigTransfer())
            ->setStemmerLanguage('light_german')
            ->setSynonyms(new ArrayObject([$this->term('stuhl => stuhl, sessel')]));

        $baseSettings = [
            'analysis' => [
                'analyzer' => [
                    'fulltext_search_analyzer' => [
                        'tokenizer' => 'standard',
                        // sac_stopwords is present but unrelated to either active field here -- just
                        // enough to keep this analyzer auto-discovered as a target while genuinely lacking
                        // both sac_stemmer and sac_synonyms.
                        'filter' => ['lowercase', 'sac_stopwords'],
                    ],
                ],
            ],
        ];

        $warnings = (new SearchAnalyzerConfigRenderer())->collectMissingSlotWarnings($searchAnalyzerConfigTransfer, $baseSettings);

        $this->assertCount(2, $warnings);
    }

    public function testInactiveFieldWithNoReferencedSlotDoesNotThrowAndWritesNothing(): void
    {
        $baseSettings = [
            'analysis' => [
                'analyzer' => [
                    'fulltext_search_analyzer' => [
                        'tokenizer' => 'standard',
                        'filter' => ['lowercase', 'sac_synonyms'],
                    ],
                ],
            ],
        ];

        $result = (new SearchAnalyzerConfigRenderer())->render(new SearchAnalyzerConfigTransfer(), $baseSettings);

        $this->assertSame(
            ['type' => 'synonym', 'synonyms' => []],
            $result['analysis']['filter']['sac_synonyms'],
        );
        $this->assertArrayNotHasKey('sac_stemmer', $result['analysis']['filter']);
    }

    public function testSynonymsAreWrittenIntoAnAlreadyReferencedSynonymSlot(): void
    {
        $searchAnalyzerConfigTransfer = (new SearchAnalyzerConfigTransfer())
            ->setSynonyms(new ArrayObject([$this->term('stuhl => stuhl, sessel'), $this->term('leuchte => leuchte, lampe')]));

        $result = (new SearchAnalyzerConfigRenderer())->render($searchAnalyzerConfigTransfer, static::FULLY_SLOTTED_BASE_SETTINGS);

        $this->assertSame(
            ['type' => 'synonym', 'synonyms' => ['stuhl => stuhl, sessel', 'leuchte => leuchte, lampe']],
            $result['analysis']['filter']['sac_synonyms'],
        );
    }

    public function testClearedSynonymsBlankAnAlreadyReferencedSlotRatherThanLeavingStaleData(): void
    {
        $baseSettings = static::FULLY_SLOTTED_BASE_SETTINGS;
        $baseSettings['analysis']['filter']['sac_synonyms'] = ['type' => 'synonym', 'synonyms' => ['stale => data']];

        $result = (new SearchAnalyzerConfigRenderer())->render(new SearchAnalyzerConfigTransfer(), $baseSettings);

        $this->assertSame(
            ['type' => 'synonym', 'synonyms' => []],
            $result['analysis']['filter']['sac_synonyms'],
        );
    }

    public function testBuiltinStopwordsModeUsesLanguageStringNotTermList(): void
    {
        $searchAnalyzerConfigTransfer = (new SearchAnalyzerConfigTransfer())
            ->setStopwordsMode(SearchAnalyzerConfigConfig::STOPWORDS_MODE_BUILTIN)
            ->setStopwordsBuiltinLanguage('_german_')
            ->setStopwords(new ArrayObject([$this->term('ignored')]));

        $result = (new SearchAnalyzerConfigRenderer())->render($searchAnalyzerConfigTransfer, static::FULLY_SLOTTED_BASE_SETTINGS);

        $this->assertSame(
            ['type' => 'stop', 'stopwords' => '_german_'],
            $result['analysis']['filter']['sac_stopwords'],
        );
    }

    public function testCustomStopwordsModeUsesTermList(): void
    {
        $searchAnalyzerConfigTransfer = (new SearchAnalyzerConfigTransfer())
            ->setStopwordsMode(SearchAnalyzerConfigConfig::STOPWORDS_MODE_CUSTOM)
            ->setStopwords(new ArrayObject([$this->term('und'), $this->term('oder')]));

        $result = (new SearchAnalyzerConfigRenderer())->render($searchAnalyzerConfigTransfer, static::FULLY_SLOTTED_BASE_SETTINGS);

        $this->assertSame(
            ['type' => 'stop', 'stopwords' => ['und', 'oder']],
            $result['analysis']['filter']['sac_stopwords'],
        );
    }

    public function testStopwordsModeNoneDoesNotRequireTheSlotToBeReferenced(): void
    {
        $searchAnalyzerConfigTransfer = (new SearchAnalyzerConfigTransfer())
            ->setStopwordsMode(SearchAnalyzerConfigConfig::STOPWORDS_MODE_NONE);

        $baseSettings = [
            'analysis' => [
                'analyzer' => [
                    'fulltext_search_analyzer' => [
                        'tokenizer' => 'standard',
                        'filter' => ['lowercase', 'sac_synonyms'],
                    ],
                ],
            ],
        ];

        $result = (new SearchAnalyzerConfigRenderer())->render($searchAnalyzerConfigTransfer, $baseSettings);

        $this->assertArrayNotHasKey('sac_stopwords', $result['analysis']['filter'] ?? []);
    }

    public function testDecompoundEnabledWritesBothTheOuterConditionSlotAndItsNonChainVisibleInnerFilter(): void
    {
        $searchAnalyzerConfigTransfer = (new SearchAnalyzerConfigTransfer())
            ->setDecompoundEnabled(true)
            ->setDecompoundWords(new ArrayObject([$this->term('brenn'), $this->term('stuhl')]));

        $result = (new SearchAnalyzerConfigRenderer())->render($searchAnalyzerConfigTransfer, static::FULLY_SLOTTED_BASE_SETTINGS);

        $this->assertSame(
            ['type' => 'dictionary_decompounder', 'word_list' => ['brenn', 'stuhl']],
            $result['analysis']['filter']['sac_decompound_words'],
        );
        $conditionFilter = $result['analysis']['filter']['sac_decompound'];
        $this->assertSame('condition', $conditionFilter['type']);
        $this->assertSame(['sac_decompound_words'], $conditionFilter['filter']);
    }

    public function testDoNotDecompoundTermsAreInlinedAsScriptLiteralsIntoTheConditionSlot(): void
    {
        $searchAnalyzerConfigTransfer = (new SearchAnalyzerConfigTransfer())
            ->setDecompoundEnabled(true)
            ->setDecompoundWords(new ArrayObject([$this->term('brenn')]))
            ->setDoNotDecompoundTerms(new ArrayObject([$this->term('Brennenstuhl'), $this->term('Voltraxx')]));

        $result = (new SearchAnalyzerConfigRenderer())->render($searchAnalyzerConfigTransfer, static::FULLY_SLOTTED_BASE_SETTINGS);

        $script = $result['analysis']['filter']['sac_decompound']['script']['source'];
        $this->assertStringContainsString('"brennenstuhl"', $script);
        $this->assertStringContainsString('"voltraxx"', $script);
        $this->assertStringContainsString('!sacBrands.contains(', $script);
    }

    public function testDecompoundDisabledStillBlanksAnAlreadyReferencedSlotToAnAlwaysTrueCondition(): void
    {
        $baseSettings = static::FULLY_SLOTTED_BASE_SETTINGS;
        $baseSettings['analysis']['filter']['sac_decompound_words'] = ['type' => 'dictionary_decompounder', 'word_list' => ['stale']];

        $result = (new SearchAnalyzerConfigRenderer())->render(new SearchAnalyzerConfigTransfer(), $baseSettings);

        $this->assertSame(
            ['type' => 'dictionary_decompounder', 'word_list' => []],
            $result['analysis']['filter']['sac_decompound_words'],
        );
    }

    /**
     * The exact case this relaxation is FOR: a project wires decompounding into some target analyzers and
     * deliberately leaves it out of others (e.g. an index-time analyzer gets it, a search-time one
     * doesn't) -- the analyzer without the slot just doesn't get decompounding, no throw, no error.
     */
    public function testActiveDecompoundWithoutAReferencedSlotOnOneAnalyzerIsSkippedThereButStillAppliedOnAnother(): void
    {
        $searchAnalyzerConfigTransfer = (new SearchAnalyzerConfigTransfer())
            ->setDecompoundEnabled(true)
            ->setDecompoundWords(new ArrayObject([$this->term('brenn')]));

        $baseSettings = [
            'analysis' => [
                'analyzer' => [
                    'fulltext_search_analyzer' => [
                        'tokenizer' => 'standard',
                        'filter' => ['lowercase', 'sac_synonyms'],
                    ],
                    'fulltext_index_analyzer' => [
                        'tokenizer' => 'standard',
                        'filter' => ['lowercase', 'sac_decompound'],
                    ],
                ],
            ],
        ];

        $result = (new SearchAnalyzerConfigRenderer())->render($searchAnalyzerConfigTransfer, $baseSettings);

        $this->assertArrayHasKey('sac_decompound', $result['analysis']['filter']);
    }

    public function testActiveDecompoundWithoutAReferencedSlotWarnsButDoesNotThrow(): void
    {
        $searchAnalyzerConfigTransfer = (new SearchAnalyzerConfigTransfer())
            ->setDecompoundEnabled(true)
            ->setDecompoundWords(new ArrayObject([$this->term('brenn')]));

        $baseSettings = [
            'analysis' => [
                'analyzer' => [
                    'fulltext_search_analyzer' => [
                        'tokenizer' => 'standard',
                        'filter' => ['lowercase', 'sac_synonyms'],
                    ],
                ],
            ],
        ];

        $result = (new SearchAnalyzerConfigRenderer())->render($searchAnalyzerConfigTransfer, $baseSettings);
        $this->assertArrayNotHasKey('sac_decompound', $result['analysis']['filter'] ?? []);

        $warnings = (new SearchAnalyzerConfigRenderer())->collectMissingSlotWarnings($searchAnalyzerConfigTransfer, $baseSettings);
        $this->assertCount(1, $warnings);
        $this->assertStringContainsString('sac_decompound', $warnings[0]);
    }

    public function testKeywordMarkerRequiresBothADoNotDecompoundListAndAStemmerLanguage(): void
    {
        $searchAnalyzerConfigTransfer = (new SearchAnalyzerConfigTransfer())
            ->setStemmerLanguage('light_german')
            ->setDoNotDecompoundTerms(new ArrayObject([$this->term('Brennenstuhl')]));

        $result = (new SearchAnalyzerConfigRenderer())->render($searchAnalyzerConfigTransfer, static::FULLY_SLOTTED_BASE_SETTINGS);

        $this->assertSame(
            ['type' => 'keyword_marker', 'keywords' => ['Brennenstuhl']],
            $result['analysis']['filter']['sac_keyword_marker'],
        );
    }

    public function testWithoutStemmerLanguageKeywordMarkerFieldIsInactiveEvenWithDoNotDecompoundTerms(): void
    {
        $searchAnalyzerConfigTransfer = (new SearchAnalyzerConfigTransfer())
            ->setDoNotDecompoundTerms(new ArrayObject([$this->term('Brennenstuhl')]));

        $baseSettings = [
            'analysis' => [
                'analyzer' => [
                    'fulltext_search_analyzer' => [
                        'tokenizer' => 'standard',
                        'filter' => ['lowercase', 'sac_synonyms'],
                    ],
                ],
            ],
        ];

        $result = (new SearchAnalyzerConfigRenderer())->render($searchAnalyzerConfigTransfer, $baseSettings);

        $this->assertArrayNotHasKey('sac_keyword_marker', $result['analysis']['filter'] ?? []);
    }

    public function testNormalizationFilterUnsetLeavesTheSlotUntouched(): void
    {
        $baseSettings = static::FULLY_SLOTTED_BASE_SETTINGS;
        $baseSettings['analysis']['filter']['sac_normalization'] = ['type' => 'german_normalization'];

        $result = (new SearchAnalyzerConfigRenderer())->render(new SearchAnalyzerConfigTransfer(), $baseSettings);

        $this->assertSame(
            ['type' => 'german_normalization'],
            $result['analysis']['filter']['sac_normalization'],
        );
    }

    public function testNormalizationFilterSetOverwritesAnAlreadyReferencedSlot(): void
    {
        $searchAnalyzerConfigTransfer = (new SearchAnalyzerConfigTransfer())
            ->setNormalizationFilter('arabic_normalization');

        $result = (new SearchAnalyzerConfigRenderer())->render($searchAnalyzerConfigTransfer, static::FULLY_SLOTTED_BASE_SETTINGS);

        $this->assertSame(
            ['type' => 'arabic_normalization'],
            $result['analysis']['filter']['sac_normalization'],
        );
    }

    /**
     * Belt-and-suspenders: even though SearchAnalyzerConfigValidator is the real enforcement point, the
     * renderer independently refuses to inline a term that wouldn't match SearchAnalyzerConfigConfig::TERM_PATTERN
     * into Painless script source, since that inlining is a script-injection boundary.
     */
    public function testInvalidDoNotDecompoundTermThrowsInsteadOfBeingInlinedIntoScriptSource(): void
    {
        $searchAnalyzerConfigTransfer = (new SearchAnalyzerConfigTransfer())
            ->setDecompoundEnabled(true)
            ->setDecompoundWords(new ArrayObject([$this->term('brenn')]))
            ->setDoNotDecompoundTerms(new ArrayObject([$this->term('"]; malicious.code(); def x = [')]));

        $this->expectException(SearchAnalyzerConfigInvalidTermException::class);

        (new SearchAnalyzerConfigRenderer())->render($searchAnalyzerConfigTransfer, static::FULLY_SLOTTED_BASE_SETTINGS);
    }

    /**
     * @param string $term
     */
    protected function term(string $term): SearchAnalyzerConfigTermTransfer
    {
        return (new SearchAnalyzerConfigTermTransfer())->setTerm($term);
    }
}
