<?php

/**
 * This file is part of the spryker-community/search-analyzer-config package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchAnalyzerConfig\Communication\Mapper;

use ArrayObject;
use Generated\Shared\Transfer\SearchAnalyzerConfigTermTransfer;
use Generated\Shared\Transfer\SearchAnalyzerConfigTransfer;
use SprykerCommunity\Shared\SearchAnalyzerConfig\SearchAnalyzerConfigConfig;
use SprykerCommunity\Zed\SearchAnalyzerConfig\Communication\Form\SearchAnalyzerConfigEditForm;

/**
 * The plain-text <-> \Generated\Shared\Transfer\SearchAnalyzerConfigTermTransfer conversion used by every
 * term-list-carrying filter page (stopwords/decompound inline, plus the standalone synonym/do-not-decompound
 * pages) -- factored out of ConfigController so that shared, generic mapping logic doesn't count against the
 * controller's own complexity budget.
 */
class SearchAnalyzerConfigTermListMapper
{
    /**
     * @param \Generated\Shared\Transfer\SearchAnalyzerConfigTransfer $searchAnalyzerConfigTransfer
     * @param string $listType One of SearchAnalyzerConfigConfig::LIST_TYPES.
     *
     * @return \ArrayObject<int, \Generated\Shared\Transfer\SearchAnalyzerConfigTermTransfer>
     */
    public function getListTerms(SearchAnalyzerConfigTransfer $searchAnalyzerConfigTransfer, string $listType): ArrayObject
    {
        return match ($listType) {
            SearchAnalyzerConfigConfig::LIST_TYPE_SYNONYM => $searchAnalyzerConfigTransfer->getSynonyms(),
            SearchAnalyzerConfigConfig::LIST_TYPE_DECOMPOUND_WORD => $searchAnalyzerConfigTransfer->getDecompoundWords(),
            SearchAnalyzerConfigConfig::LIST_TYPE_STOPWORD => $searchAnalyzerConfigTransfer->getStopwords(),
            SearchAnalyzerConfigConfig::LIST_TYPE_DO_NOT_DECOMPOUND => $searchAnalyzerConfigTransfer->getDoNotDecompoundTerms(),
            default => new ArrayObject(),
        };
    }

    /**
     * @param \Generated\Shared\Transfer\SearchAnalyzerConfigTransfer $searchAnalyzerConfigTransfer
     * @param string $listType One of SearchAnalyzerConfigConfig::LIST_TYPES.
     * @param \ArrayObject<int, \Generated\Shared\Transfer\SearchAnalyzerConfigTermTransfer> $terms
     */
    public function setListTerms(SearchAnalyzerConfigTransfer $searchAnalyzerConfigTransfer, string $listType, ArrayObject $terms): void
    {
        match ($listType) {
            SearchAnalyzerConfigConfig::LIST_TYPE_SYNONYM => $searchAnalyzerConfigTransfer->setSynonyms($terms),
            SearchAnalyzerConfigConfig::LIST_TYPE_DECOMPOUND_WORD => $searchAnalyzerConfigTransfer->setDecompoundWords($terms),
            SearchAnalyzerConfigConfig::LIST_TYPE_STOPWORD => $searchAnalyzerConfigTransfer->setStopwords($terms),
            SearchAnalyzerConfigConfig::LIST_TYPE_DO_NOT_DECOMPOUND => $searchAnalyzerConfigTransfer->setDoNotDecompoundTerms($terms),
            default => null,
        };
    }

    /**
     * @param iterable<\Generated\Shared\Transfer\SearchAnalyzerConfigTermTransfer> $searchAnalyzerConfigTermTransfers
     */
    public function termsToText(iterable $searchAnalyzerConfigTermTransfers): string
    {
        $terms = [];

        foreach ($searchAnalyzerConfigTermTransfers as $searchAnalyzerConfigTermTransfer) {
            $terms[] = $searchAnalyzerConfigTermTransfer->getTerm();
        }

        return implode("\n", $terms);
    }

    /**
     * @param string $text
     * @param string $listType
     *
     * @return \ArrayObject<int, \Generated\Shared\Transfer\SearchAnalyzerConfigTermTransfer>
     */
    public function textToTerms(string $text, string $listType): ArrayObject
    {
        $searchAnalyzerConfigTermTransfers = [];
        $sortOrder = 0;

        foreach (preg_split('/\r\n|\r|\n/', $text) ?: [] as $line) {
            $term = trim($line);

            if ($term === '') {
                continue;
            }

            $searchAnalyzerConfigTermTransfers[] = (new SearchAnalyzerConfigTermTransfer())
                ->setListType($listType)
                ->setTerm($term)
                ->setSortOrder($sortOrder++);
        }

        return new ArrayObject($searchAnalyzerConfigTermTransfers);
    }

    public function nullIfEmpty(string $value): ?string
    {
        return $value === '' ? null : $value;
    }

    /**
     * The two SearchAnalyzerConfigConfig::STANDALONE_LIST_TYPES each map to their own slot -- note
     * LIST_TYPE_DO_NOT_DECOMPOUND's slot is `sac_keyword_marker`, not a "do-not-decompound"-named filter:
     * the underlying OpenSearch/Elasticsearch mechanism is a `keyword_marker` filter (it protects a term
     * from a later STEMMER, independent of whether decompounding is even enabled -- see
     * SearchAnalyzerConfigRenderer::applyKeywordMarkerSlot()'s own doc block).
     *
     * @param string $listType One of SearchAnalyzerConfigConfig::STANDALONE_LIST_TYPES.
     */
    public function resolveStandaloneListSlotName(string $listType): string
    {
        return match ($listType) {
            SearchAnalyzerConfigConfig::LIST_TYPE_SYNONYM => 'sac_synonyms',
            SearchAnalyzerConfigConfig::LIST_TYPE_DO_NOT_DECOMPOUND => 'sac_keyword_marker',
            default => '',
        };
    }

    /**
     * @param \Generated\Shared\Transfer\SearchAnalyzerConfigTransfer $searchAnalyzerConfigTransfer
     * @param string|null $listType The one list type (if any) whose terms should also be included, as
     *  SearchAnalyzerConfigEditForm::FIELD_LIST_TEXT -- see ConfigController::resolveFilterListType().
     *
     * @return array<string, mixed>
     */
    public function transferToFormData(SearchAnalyzerConfigTransfer $searchAnalyzerConfigTransfer, ?string $listType = null): array
    {
        $formData = [
            SearchAnalyzerConfigEditForm::FIELD_SOURCE_IDENTIFIER => $searchAnalyzerConfigTransfer->getSourceIdentifier(),
            SearchAnalyzerConfigEditForm::FIELD_STORE_NAME => $searchAnalyzerConfigTransfer->getStoreName(),
            SearchAnalyzerConfigEditForm::FIELD_STEMMER_LANGUAGE => $searchAnalyzerConfigTransfer->getStemmerLanguage() ?? '',
            SearchAnalyzerConfigEditForm::FIELD_NORMALIZATION_FILTER => $searchAnalyzerConfigTransfer->getNormalizationFilter() ?? '',
            SearchAnalyzerConfigEditForm::FIELD_STOPWORDS_MODE => $searchAnalyzerConfigTransfer->getStopwordsMode() ?? SearchAnalyzerConfigConfig::STOPWORDS_MODE_NONE,
            SearchAnalyzerConfigEditForm::FIELD_STOPWORDS_BUILTIN_LANGUAGE => $searchAnalyzerConfigTransfer->getStopwordsBuiltinLanguage() ?? '',
            SearchAnalyzerConfigEditForm::FIELD_DECOMPOUND_ENABLED => (bool)$searchAnalyzerConfigTransfer->getDecompoundEnabled(),
        ];

        if ($listType !== null) {
            $formData[SearchAnalyzerConfigEditForm::FIELD_LIST_TEXT] = $this->termsToText($this->getListTerms($searchAnalyzerConfigTransfer, $listType));
        }

        return $formData;
    }
}
