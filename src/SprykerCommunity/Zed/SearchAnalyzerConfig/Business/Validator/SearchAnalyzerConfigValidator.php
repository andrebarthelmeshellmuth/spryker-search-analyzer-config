<?php

/**
 * This file is part of the spryker-community/search-analyzer-config package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchAnalyzerConfig\Business\Validator;

use Generated\Shared\Transfer\SearchAnalyzerConfigTermTransfer;
use Generated\Shared\Transfer\SearchAnalyzerConfigTransfer;
use SprykerCommunity\Shared\SearchAnalyzerConfig\SearchAnalyzerConfigConfig;

/**
 * Every term destined for the do-not-decompound list ends up inlined as a Painless list literal in a
 * `condition` filter's `script.source` -- the `analysis` script context does not support `script.params`
 * (verified live, see [[search_analyzer_config_plan]]). That makes this validator a security boundary
 * (preventing script injection via a persisted term), not just input hygiene, which is why it rejects
 * anything outside SearchAnalyzerConfigConfig::TERM_PATTERN for that one list type rather than merely
 * escaping it.
 */
class SearchAnalyzerConfigValidator implements SearchAnalyzerConfigValidatorInterface
{
    /**
     * @param \Generated\Shared\Transfer\SearchAnalyzerConfigTransfer $searchAnalyzerConfigTransfer
     *
     * @return array<string>
     */
    public function validate(SearchAnalyzerConfigTransfer $searchAnalyzerConfigTransfer): array
    {
        $errors = [];

        $errors = [...$errors, ...$this->validateStopwordsMode($searchAnalyzerConfigTransfer)];
        $errors = [...$errors, ...$this->validateDoNotDecompoundTerms($searchAnalyzerConfigTransfer)];
        $errors = [...$errors, ...$this->validateNoBlankTerms($searchAnalyzerConfigTransfer)];

        return [...$errors, ...$this->validateDecompoundRequiresWords($searchAnalyzerConfigTransfer)];
    }

    /**
     * The Renderer silently trims and skips a blank/whitespace-only term, so one reaching storage at all
     * would otherwise be double-counted: shown as an active term everywhere that reads the raw list
     * (GUI term count, `show` console, revision history), yet never actually reach the live analyzer. The
     * GUI form itself already strips blank lines before building the transfer, so this only ever fires
     * for a caller that bypasses that (e.g. `Facade::save()` used directly).
     *
     * @param \Generated\Shared\Transfer\SearchAnalyzerConfigTransfer $searchAnalyzerConfigTransfer
     *
     * @return array<string>
     */
    protected function validateNoBlankTerms(SearchAnalyzerConfigTransfer $searchAnalyzerConfigTransfer): array
    {
        $errors = [];

        foreach ($this->collectTermListsByLabel($searchAnalyzerConfigTransfer) as $label => $searchAnalyzerConfigTermTransfers) {
            foreach ($searchAnalyzerConfigTermTransfers as $searchAnalyzerConfigTermTransfer) {
                if ($this->getTerm($searchAnalyzerConfigTermTransfer) === '') {
                    $errors[] = sprintf('%s contains a blank or whitespace-only term, which is not allowed.', $label);

                    break;
                }
            }
        }

        return $errors;
    }

    /**
     * @param \Generated\Shared\Transfer\SearchAnalyzerConfigTransfer $searchAnalyzerConfigTransfer
     *
     * @return array<string, iterable<\Generated\Shared\Transfer\SearchAnalyzerConfigTermTransfer>>
     */
    protected function collectTermListsByLabel(SearchAnalyzerConfigTransfer $searchAnalyzerConfigTransfer): array
    {
        return [
            'Stopwords' => $searchAnalyzerConfigTransfer->getStopwords(),
            'Decompound words' => $searchAnalyzerConfigTransfer->getDecompoundWords(),
            'Do-not-decompound terms' => $searchAnalyzerConfigTransfer->getDoNotDecompoundTerms(),
            'Synonyms' => $searchAnalyzerConfigTransfer->getSynonyms(),
        ];
    }

    /**
     * @param \Generated\Shared\Transfer\SearchAnalyzerConfigTransfer $searchAnalyzerConfigTransfer
     *
     * @return array<string>
     */
    protected function validateStopwordsMode(SearchAnalyzerConfigTransfer $searchAnalyzerConfigTransfer): array
    {
        $stopwordsMode = $searchAnalyzerConfigTransfer->getStopwordsMode();

        if ($stopwordsMode !== null && !in_array($stopwordsMode, SearchAnalyzerConfigConfig::STOPWORDS_MODES, true)) {
            return [sprintf('Unknown stopwords mode "%s".', $stopwordsMode)];
        }

        if (
            $stopwordsMode === SearchAnalyzerConfigConfig::STOPWORDS_MODE_BUILTIN
            && !$searchAnalyzerConfigTransfer->getStopwordsBuiltinLanguage()
        ) {
            return ['Stopwords mode is "builtin" but no builtin language is selected.'];
        }

        return [];
    }

    /**
     * @param \Generated\Shared\Transfer\SearchAnalyzerConfigTransfer $searchAnalyzerConfigTransfer
     *
     * @return array<string>
     */
    protected function validateDoNotDecompoundTerms(SearchAnalyzerConfigTransfer $searchAnalyzerConfigTransfer): array
    {
        $errors = [];

        foreach ($searchAnalyzerConfigTransfer->getDoNotDecompoundTerms() as $searchAnalyzerConfigTermTransfer) {
            $term = $this->getTerm($searchAnalyzerConfigTermTransfer);

            if ($term === '' || preg_match(SearchAnalyzerConfigConfig::TERM_PATTERN, $term) === 1) {
                continue;
            }

            $errors[] = sprintf(
                'Do-not-decompound term "%s" contains characters that are not allowed (letters, digits, "_", "-", "." only).',
                $term,
            );
        }

        return $errors;
    }

    /**
     * @param \Generated\Shared\Transfer\SearchAnalyzerConfigTransfer $searchAnalyzerConfigTransfer
     *
     * @return array<string>
     */
    protected function validateDecompoundRequiresWords(SearchAnalyzerConfigTransfer $searchAnalyzerConfigTransfer): array
    {
        if ($searchAnalyzerConfigTransfer->getDecompoundEnabled() !== true) {
            return [];
        }

        foreach ($searchAnalyzerConfigTransfer->getDecompoundWords() as $searchAnalyzerConfigTermTransfer) {
            if ($this->getTerm($searchAnalyzerConfigTermTransfer) !== '') {
                return [];
            }
        }

        return ['Decompounding is enabled but the decompound word list is empty.'];
    }

    /**
     * @param \Generated\Shared\Transfer\SearchAnalyzerConfigTermTransfer $searchAnalyzerConfigTermTransfer
     */
    protected function getTerm(SearchAnalyzerConfigTermTransfer $searchAnalyzerConfigTermTransfer): string
    {
        return trim((string)$searchAnalyzerConfigTermTransfer->getTerm());
    }
}
