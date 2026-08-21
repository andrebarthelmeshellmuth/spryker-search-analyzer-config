<?php

/**
 * This file is part of the spryker-community/search-analyzer-config package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchAnalyzerConfig\Communication\Controller;

use ArrayObject;
use Generated\Shared\Transfer\SearchAnalyzerConfigTermTransfer;
use Generated\Shared\Transfer\SearchAnalyzerConfigTransfer;
use SprykerCommunity\Shared\SearchAnalyzerConfig\SearchAnalyzerConfigConfig;
use SprykerCommunity\Zed\SearchAnalyzerConfig\Communication\Form\SearchAnalyzerConfigCopyForm;
use SprykerCommunity\Zed\SearchAnalyzerConfig\Communication\Form\SearchAnalyzerConfigEditForm;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Throwable;

class ConfigController extends AbstractScopeController
{
    /**
     * @param \Symfony\Component\HttpFoundation\Request $request
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|array<string, mixed>
     */
    public function editAction(Request $request)
    {
        $sourceIdentifier = (string)$request->query->get('source', '');
        $storeName = (string)$request->query->get('store', '');

        if ($this->findManagedScope($sourceIdentifier, $storeName) === null) {
            $this->addErrorMessage(sprintf('"%s" / "%s" is not a search-index-alias managed scope.', $sourceIdentifier, $storeName));

            return $this->redirectResponse(static::URL_INDEX);
        }

        $existingSearchAnalyzerConfigTransfer = $this->getFacade()->findByScope($sourceIdentifier, $storeName)
            ?? (new SearchAnalyzerConfigTransfer())->setSourceIdentifier($sourceIdentifier)->setStoreName($storeName);

        $editForm = $this->getFactory()
            ->createSearchAnalyzerConfigEditForm($this->transferToFormData($existingSearchAnalyzerConfigTransfer))
            ->handleRequest($request);

        if ($editForm->isSubmitted() && $editForm->isValid()) {
            /** @var array<string, mixed> $submittedData */
            $submittedData = $editForm->getData();
            $searchAnalyzerConfigTransfer = $this->formDataToTransfer($submittedData, $sourceIdentifier, $storeName);
            $confirmed = (string)$submittedData[SearchAnalyzerConfigEditForm::FIELD_CONFIRMED] === '1';

            if (!$confirmed) {
                $missingSlotWarnings = $this->getFacade()->collectMissingSlotWarnings($searchAnalyzerConfigTransfer);

                if ($missingSlotWarnings !== []) {
                    $this->addInfoMessage('Some fields are active but not referenced by every target analyzer -- review the warnings below, then click "Save anyway" to confirm and save as-is.');

                    return $this->viewResponse([
                        'sourceIdentifier' => $sourceIdentifier,
                        'storeName' => $storeName,
                        'editFormView' => $editForm->createView(),
                        'missingSlotWarnings' => $missingSlotWarnings,
                    ]);
                }
            }

            try {
                $errors = $this->getFacade()->save($searchAnalyzerConfigTransfer, SearchAnalyzerConfigConfig::CHANGE_SOURCE_MANUAL, 'zed-gui');
            } catch (Throwable $throwable) {
                $this->addErrorMessage(sprintf('Save failed: %s', $throwable->getMessage()));

                return $this->viewResponse([
                    'sourceIdentifier' => $sourceIdentifier,
                    'storeName' => $storeName,
                    'editFormView' => $editForm->createView(),
                    'missingSlotWarnings' => [],
                ]);
            }

            if ($errors === []) {
                $this->addSuccessMessage('Config staged. It is NOT live yet -- use "Apply" on the Overview page to trigger a rebuild.');

                return $this->redirectResponse($this->buildIndexUrl($sourceIdentifier, $storeName));
            }

            foreach ($errors as $error) {
                $this->addErrorMessage($error);
            }
        }

        return $this->viewResponse([
            'sourceIdentifier' => $sourceIdentifier,
            'storeName' => $storeName,
            'editFormView' => $editForm->createView(),
            'missingSlotWarnings' => [],
        ]);
    }

    /**
     * @param \Symfony\Component\HttpFoundation\Request $request
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|array<string, mixed>
     */
    public function copyAction(Request $request)
    {
        $sourceIdentifier = (string)$request->query->get('source', '');
        $storeName = (string)$request->query->get('store', '');

        if ($this->findManagedScope($sourceIdentifier, $storeName) === null) {
            $this->addErrorMessage(sprintf('"%s" / "%s" is not a search-index-alias managed scope.', $sourceIdentifier, $storeName));

            return $this->redirectResponse($this->buildIndexUrl($sourceIdentifier, $storeName));
        }

        if ($this->getFacade()->findByScope($sourceIdentifier, $storeName) === null) {
            $this->addErrorMessage(sprintf('No config staged for "%s" / "%s" -- nothing to copy.', $sourceIdentifier, $storeName));

            return $this->redirectResponse($this->buildIndexUrl($sourceIdentifier, $storeName));
        }

        $copyForm = $this->getFactory()->createSearchAnalyzerConfigCopyForm($sourceIdentifier, $storeName)->handleRequest($request);

        if ($copyForm->isSubmitted() && $copyForm->isValid()) {
            return $this->handleCopySubmit($copyForm, $sourceIdentifier, $storeName);
        }

        return $this->viewResponse([
            'sourceIdentifier' => $sourceIdentifier,
            'storeName' => $storeName,
            'copyFormView' => $copyForm->createView(),
        ]);
    }

    /**
     * @param \Symfony\Component\Form\FormInterface $copyForm
     * @param string $sourceIdentifier
     * @param string $storeName
     */
    protected function handleCopySubmit(FormInterface $copyForm, string $sourceIdentifier, string $storeName): RedirectResponse
    {
        /** @var array<string, mixed> $formData */
        $formData = $copyForm->getData();
        /** @var string $targetSourceIdentifier */
        $targetSourceIdentifier = $formData[SearchAnalyzerConfigCopyForm::FIELD_TARGET_SOURCE_IDENTIFIER];
        /** @var string $targetStoreName */
        $targetStoreName = $formData[SearchAnalyzerConfigCopyForm::FIELD_TARGET_STORE_NAME];

        if ($this->findManagedScope($targetSourceIdentifier, $targetStoreName) === null) {
            $this->addErrorMessage(sprintf('"%s" / "%s" is not a search-index-alias managed scope.', $targetSourceIdentifier, $targetStoreName));

            return $this->redirectResponse($this->buildIndexUrl($sourceIdentifier, $storeName));
        }

        $errors = $this->getFacade()->copy($sourceIdentifier, $targetSourceIdentifier, $storeName, $targetStoreName, 'zed-gui');

        if ($errors !== []) {
            foreach ($errors as $error) {
                $this->addErrorMessage($error);
            }

            return $this->redirectResponse($this->buildIndexUrl($sourceIdentifier, $storeName));
        }

        $copyResult = $this->getFacade()->findByScope($targetSourceIdentifier, $targetStoreName);

        $this->addSuccessMessage(sprintf(
            'Copied onto "%s" / "%s" (revision %d). It is NOT live yet -- use "Apply" on that scope to trigger a rebuild.',
            $targetSourceIdentifier,
            $targetStoreName,
            $copyResult?->getRevision() ?? 0,
        ));

        return $this->redirectResponse($this->buildIndexUrl($targetSourceIdentifier, $targetStoreName));
    }

    /**
     * @param \Generated\Shared\Transfer\SearchAnalyzerConfigTransfer $searchAnalyzerConfigTransfer
     *
     * @return array<string, mixed>
     */
    protected function transferToFormData(SearchAnalyzerConfigTransfer $searchAnalyzerConfigTransfer): array
    {
        return [
            SearchAnalyzerConfigEditForm::FIELD_SOURCE_IDENTIFIER => $searchAnalyzerConfigTransfer->getSourceIdentifier(),
            SearchAnalyzerConfigEditForm::FIELD_STORE_NAME => $searchAnalyzerConfigTransfer->getStoreName(),
            SearchAnalyzerConfigEditForm::FIELD_STEMMER_LANGUAGE => $searchAnalyzerConfigTransfer->getStemmerLanguage() ?? '',
            SearchAnalyzerConfigEditForm::FIELD_NORMALIZATION_FILTER => $searchAnalyzerConfigTransfer->getNormalizationFilter() ?? '',
            SearchAnalyzerConfigEditForm::FIELD_STOPWORDS_MODE => $searchAnalyzerConfigTransfer->getStopwordsMode() ?? SearchAnalyzerConfigConfig::STOPWORDS_MODE_NONE,
            SearchAnalyzerConfigEditForm::FIELD_STOPWORDS_BUILTIN_LANGUAGE => $searchAnalyzerConfigTransfer->getStopwordsBuiltinLanguage() ?? '',
            SearchAnalyzerConfigEditForm::FIELD_STOPWORDS_CUSTOM_TEXT => $this->termsToText($searchAnalyzerConfigTransfer->getStopwords()),
            SearchAnalyzerConfigEditForm::FIELD_DECOMPOUND_ENABLED => (bool)$searchAnalyzerConfigTransfer->getDecompoundEnabled(),
            SearchAnalyzerConfigEditForm::FIELD_DECOMPOUND_WORDS_TEXT => $this->termsToText($searchAnalyzerConfigTransfer->getDecompoundWords()),
            SearchAnalyzerConfigEditForm::FIELD_DO_NOT_DECOMPOUND_TERMS_TEXT => $this->termsToText($searchAnalyzerConfigTransfer->getDoNotDecompoundTerms()),
            SearchAnalyzerConfigEditForm::FIELD_SYNONYMS_TEXT => $this->termsToText($searchAnalyzerConfigTransfer->getSynonyms()),
        ];
    }

    /**
     * @param array<string, mixed> $formData
     * @param string $sourceIdentifier
     * @param string $storeName
     */
    protected function formDataToTransfer(array $formData, string $sourceIdentifier, string $storeName): SearchAnalyzerConfigTransfer
    {
        $stopwordsMode = (string)$formData[SearchAnalyzerConfigEditForm::FIELD_STOPWORDS_MODE];

        return (new SearchAnalyzerConfigTransfer())
            ->setSourceIdentifier($sourceIdentifier)
            ->setStoreName($storeName)
            ->setStemmerLanguage($this->nullIfEmpty((string)$formData[SearchAnalyzerConfigEditForm::FIELD_STEMMER_LANGUAGE]))
            ->setNormalizationFilter($this->nullIfEmpty((string)$formData[SearchAnalyzerConfigEditForm::FIELD_NORMALIZATION_FILTER]))
            ->setStopwordsMode($stopwordsMode)
            ->setStopwordsBuiltinLanguage($this->nullIfEmpty((string)$formData[SearchAnalyzerConfigEditForm::FIELD_STOPWORDS_BUILTIN_LANGUAGE]))
            ->setStopwords($stopwordsMode === SearchAnalyzerConfigConfig::STOPWORDS_MODE_CUSTOM
                ? $this->textToTerms((string)$formData[SearchAnalyzerConfigEditForm::FIELD_STOPWORDS_CUSTOM_TEXT], SearchAnalyzerConfigConfig::LIST_TYPE_STOPWORD)
                : new ArrayObject())
            ->setDecompoundEnabled((bool)$formData[SearchAnalyzerConfigEditForm::FIELD_DECOMPOUND_ENABLED])
            ->setDecompoundWords($this->textToTerms((string)$formData[SearchAnalyzerConfigEditForm::FIELD_DECOMPOUND_WORDS_TEXT], SearchAnalyzerConfigConfig::LIST_TYPE_DECOMPOUND_WORD))
            ->setDoNotDecompoundTerms($this->textToTerms((string)$formData[SearchAnalyzerConfigEditForm::FIELD_DO_NOT_DECOMPOUND_TERMS_TEXT], SearchAnalyzerConfigConfig::LIST_TYPE_DO_NOT_DECOMPOUND))
            ->setSynonyms($this->textToTerms((string)$formData[SearchAnalyzerConfigEditForm::FIELD_SYNONYMS_TEXT], SearchAnalyzerConfigConfig::LIST_TYPE_SYNONYM));
    }

    /**
     * @param iterable<\Generated\Shared\Transfer\SearchAnalyzerConfigTermTransfer> $searchAnalyzerConfigTermTransfers
     */
    protected function termsToText(iterable $searchAnalyzerConfigTermTransfers): string
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
    protected function textToTerms(string $text, string $listType): ArrayObject
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

    protected function nullIfEmpty(string $value): ?string
    {
        return $value === '' ? null : $value;
    }
}
