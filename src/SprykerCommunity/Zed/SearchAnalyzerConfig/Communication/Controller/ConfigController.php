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
use SprykerCommunity\Zed\SearchAnalyzerConfig\Communication\Form\SearchAnalyzerConfigEditListForm;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Throwable;

class ConfigController extends AbstractScopeController
{
    /**
     * Read-only overview: the slot x analyzer matrix (column 1 carries the human-readable current value
     * and the one "Edit" link per row -- see edit.twig's own note on why editing is per-filter now, not
     * per-analyzer) plus the two list-only rows' term counts. Every actual edit happens on its own small
     * page -- editFilterAction() for the four scalar-backed filters, editListAction() for the four term
     * lists (including the two list-only rows, sac_keyword_marker/sac_synonyms, that have no scalar page
     * of their own).
     *
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

        $targetAnalyzerNames = $this->getFacade()->getManagedAnalyzerNames($sourceIdentifier, $storeName);

        // Structural, independent of any field's current value -- shown up front so the user sees which
        // analyzers a slot is missing on before ever opening that filter's own edit page.
        $slotAvailability = $this->getFacade()->describeSlotAvailability($sourceIdentifier, $storeName);

        // Diffed against the REAL live cluster, not applied_revision -- see
        // SearchAnalyzerConfigPreviewer::describeEditedSlots()'s own doc block for why: this package has
        // no rollout-completion hook, so applied_revision can go stale or never get set at all. This stays
        // accurate regardless.
        $editedSlots = $this->getFacade()->describeEditedSlots($sourceIdentifier, $storeName, $existingSearchAnalyzerConfigTransfer);
        $hasLiveDiff = in_array(true, $editedSlots, true);

        // A real revision number to restore TO -- separate from $hasLiveDiff above, since a scope that's
        // never been through Apply has no recorded "applied" revision at all, even if its staged config
        // already differs from live (e.g. hand-built via console before this GUI ever ran).
        $appliedRevision = $existingSearchAnalyzerConfigTransfer->getAppliedRevision();

        return $this->viewResponse([
            'sourceIdentifier' => $sourceIdentifier,
            'storeName' => $storeName,
            'targetAnalyzerNames' => $targetAnalyzerNames,
            'slotAvailability' => $slotAvailability,
            'filterRows' => $this->buildFilterRows($existingSearchAnalyzerConfigTransfer, $editedSlots, $sourceIdentifier, $storeName),
            'hasLiveDiff' => $hasLiveDiff,
            'resetFormView' => ($hasLiveDiff && $appliedRevision !== null)
                ? $this->getFactory()->createSearchAnalyzerConfigRestoreForm($sourceIdentifier, $storeName, $appliedRevision, $this->buildEditUrl($sourceIdentifier, $storeName))->createView()
                : null,
        ]);
    }

    /**
     * One small page per scalar-backed filter (SearchAnalyzerConfigConfig::FILTER_TYPES) -- editing it is
     * always a SHARED, store/locale-scoped value, exactly like the matrix on the overview page already
     * explains; this page just gives it room of its own instead of cramming five fields onto one page.
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|array<string, mixed>
     */
    public function editFilterAction(Request $request)
    {
        $sourceIdentifier = (string)$request->query->get('source', '');
        $storeName = (string)$request->query->get('store', '');
        $filterType = (string)$request->query->get('filter', '');

        if ($this->findManagedScope($sourceIdentifier, $storeName) === null) {
            $this->addErrorMessage(sprintf('"%s" / "%s" is not a search-index-alias managed scope.', $sourceIdentifier, $storeName));

            return $this->redirectResponse(static::URL_INDEX);
        }

        if (!in_array($filterType, SearchAnalyzerConfigConfig::FILTER_TYPES, true)) {
            $this->addErrorMessage(sprintf('Unknown filter type "%s".', $filterType));

            return $this->redirectResponse($this->buildEditUrl($sourceIdentifier, $storeName));
        }

        $existingSearchAnalyzerConfigTransfer = $this->getFacade()->findByScope($sourceIdentifier, $storeName)
            ?? (new SearchAnalyzerConfigTransfer())->setSourceIdentifier($sourceIdentifier)->setStoreName($storeName);

        $fields = $this->resolveFilterFields($filterType);
        $slotName = $this->resolveFilterSlotName($filterType);
        $listType = $this->resolveFilterListType($filterType);
        $listTextLabel = $listType !== null ? $this->getFactory()->getEditListLabel($listType) : 'Terms (one per line)';

        $editForm = $this->getFactory()
            ->createSearchAnalyzerConfigEditForm($this->transferToFormData($existingSearchAnalyzerConfigTransfer, $listType), $fields, $listTextLabel)
            ->handleRequest($request);

        $viewVars = [
            'sourceIdentifier' => $sourceIdentifier,
            'storeName' => $storeName,
            'filterType' => $filterType,
            'filterLabel' => $this->resolveFilterLabel($filterType),
            'slotName' => $slotName,
            'editFormView' => $editForm->createView(),
            'slotAvailability' => $this->getFacade()->describeSlotAvailability($sourceIdentifier, $storeName),
            'missingSlotWarnings' => [],
        ];

        if ($editForm->isSubmitted() && $editForm->isValid()) {
            /** @var array<string, mixed> $submittedData */
            $submittedData = $editForm->getData();
            $searchAnalyzerConfigTransfer = $this->formDataToTransfer($submittedData, $existingSearchAnalyzerConfigTransfer, $sourceIdentifier, $storeName, $fields, $listType);
            $confirmed = (string)$submittedData[SearchAnalyzerConfigEditForm::FIELD_CONFIRMED] === '1';

            if (!$confirmed) {
                $missingSlotWarnings = $this->filterWarningsBySlot(
                    $this->getFacade()->collectMissingSlotWarnings($searchAnalyzerConfigTransfer),
                    $slotName,
                );

                if ($missingSlotWarnings !== []) {
                    $this->addInfoMessage('This field is active but not referenced by every analyzer -- review the warning below, then click "Save anyway" to confirm and save as-is.');

                    return $this->viewResponse(['missingSlotWarnings' => $missingSlotWarnings] + $viewVars);
                }
            }

            try {
                $errors = $this->getFacade()->save($searchAnalyzerConfigTransfer, SearchAnalyzerConfigConfig::CHANGE_SOURCE_MANUAL, 'zed-gui');
            } catch (Throwable $throwable) {
                $this->addErrorMessage(sprintf('Save failed: %s', $throwable->getMessage()));

                return $this->viewResponse($viewVars);
            }

            if ($errors === []) {
                $this->addSuccessMessage('Config staged. It is NOT live yet -- use "Apply" on the Overview page to trigger a rebuild.');

                return $this->redirectResponse($this->buildEditUrl($sourceIdentifier, $storeName));
            }

            foreach ($errors as $error) {
                $this->addErrorMessage($error);
            }
        }

        return $this->viewResponse($viewVars);
    }

    /**
     * @param \Symfony\Component\HttpFoundation\Request $request
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|array<string, mixed>
     */
    public function editListAction(Request $request)
    {
        $sourceIdentifier = (string)$request->query->get('source', '');
        $storeName = (string)$request->query->get('store', '');
        $listType = (string)$request->query->get('list', '');

        if ($this->findManagedScope($sourceIdentifier, $storeName) === null) {
            $this->addErrorMessage(sprintf('"%s" / "%s" is not a search-index-alias managed scope.', $sourceIdentifier, $storeName));

            return $this->redirectResponse(static::URL_INDEX);
        }

        if (!in_array($listType, SearchAnalyzerConfigConfig::STANDALONE_LIST_TYPES, true)) {
            $this->addErrorMessage(sprintf('"%s" has no standalone list page -- it\'s edited inline on its own filter page.', $listType));

            return $this->redirectResponse($this->buildEditUrl($sourceIdentifier, $storeName));
        }

        $existingSearchAnalyzerConfigTransfer = $this->getFacade()->findByScope($sourceIdentifier, $storeName)
            ?? (new SearchAnalyzerConfigTransfer())->setSourceIdentifier($sourceIdentifier)->setStoreName($storeName);

        $editListForm = $this->getFactory()
            ->createSearchAnalyzerConfigEditListForm(
                $sourceIdentifier,
                $storeName,
                $listType,
                $this->termsToText($this->getListTerms($existingSearchAnalyzerConfigTransfer, $listType)),
            )
            ->handleRequest($request);

        if ($editListForm->isSubmitted() && $editListForm->isValid()) {
            /** @var array<string, mixed> $submittedData */
            $submittedData = $editListForm->getData();
            $terms = $this->textToTerms((string)$submittedData[SearchAnalyzerConfigEditListForm::FIELD_TEXT], $listType);

            $searchAnalyzerConfigTransfer = clone $existingSearchAnalyzerConfigTransfer;
            $searchAnalyzerConfigTransfer->setSourceIdentifier($sourceIdentifier)->setStoreName($storeName);
            $this->setListTerms($searchAnalyzerConfigTransfer, $listType, $terms);

            try {
                $errors = $this->getFacade()->save($searchAnalyzerConfigTransfer, SearchAnalyzerConfigConfig::CHANGE_SOURCE_MANUAL, 'zed-gui');
            } catch (Throwable $throwable) {
                $this->addErrorMessage(sprintf('Save failed: %s', $throwable->getMessage()));

                return $this->viewResponse([
                    'sourceIdentifier' => $sourceIdentifier,
                    'storeName' => $storeName,
                    'listType' => $listType,
                    'editListFormView' => $editListForm->createView(),
                ]);
            }

            if ($errors === []) {
                $this->addSuccessMessage('List staged. It is NOT live yet -- use "Apply" on the Overview page to trigger a rebuild.');

                return $this->redirectResponse($this->buildEditUrl($sourceIdentifier, $storeName));
            }

            foreach ($errors as $error) {
                $this->addErrorMessage($error);
            }
        }

        return $this->viewResponse([
            'sourceIdentifier' => $sourceIdentifier,
            'storeName' => $storeName,
            'listType' => $listType,
            'editListFormView' => $editListForm->createView(),
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
     * Which SearchAnalyzerConfigEditForm::FIELD_* constant(s) belong to one FILTER_TYPES entry -- stopwords
     * and decompound each get FIELD_LIST_TEXT too, folding their one associated term list into the SAME
     * page/submit as the scalar field, exactly like the synonym/do-not-decompound rows are already one
     * page with nothing else to combine it with (see this form class's own doc block).
     *
     * @param string $filterType One of SearchAnalyzerConfigConfig::FILTER_TYPES.
     *
     * @return array<string>
     */
    protected function resolveFilterFields(string $filterType): array
    {
        return match ($filterType) {
            SearchAnalyzerConfigConfig::FILTER_TYPE_STEMMER => [SearchAnalyzerConfigEditForm::FIELD_STEMMER_LANGUAGE],
            SearchAnalyzerConfigConfig::FILTER_TYPE_NORMALIZATION => [SearchAnalyzerConfigEditForm::FIELD_NORMALIZATION_FILTER],
            SearchAnalyzerConfigConfig::FILTER_TYPE_STOPWORDS => [
                SearchAnalyzerConfigEditForm::FIELD_STOPWORDS_MODE,
                SearchAnalyzerConfigEditForm::FIELD_STOPWORDS_BUILTIN_LANGUAGE,
                SearchAnalyzerConfigEditForm::FIELD_LIST_TEXT,
            ],
            SearchAnalyzerConfigConfig::FILTER_TYPE_DECOMPOUND => [
                SearchAnalyzerConfigEditForm::FIELD_DECOMPOUND_ENABLED,
                SearchAnalyzerConfigEditForm::FIELD_LIST_TEXT,
            ],
            default => [],
        };
    }

    /**
     * The one term list belonging to a filter (stopwords -> custom stopword list, decompound -> decompound
     * word list) -- null for stemmer/normalization, which have no list of their own.
     *
     * @param string $filterType One of SearchAnalyzerConfigConfig::FILTER_TYPES.
     */
    protected function resolveFilterListType(string $filterType): ?string
    {
        return match ($filterType) {
            SearchAnalyzerConfigConfig::FILTER_TYPE_STOPWORDS => SearchAnalyzerConfigConfig::LIST_TYPE_STOPWORD,
            SearchAnalyzerConfigConfig::FILTER_TYPE_DECOMPOUND => SearchAnalyzerConfigConfig::LIST_TYPE_DECOMPOUND_WORD,
            default => null,
        };
    }

    /**
     * @param string $filterType One of SearchAnalyzerConfigConfig::FILTER_TYPES.
     */
    protected function resolveFilterSlotName(string $filterType): string
    {
        return match ($filterType) {
            SearchAnalyzerConfigConfig::FILTER_TYPE_STEMMER => 'sac_stemmer',
            SearchAnalyzerConfigConfig::FILTER_TYPE_NORMALIZATION => 'sac_normalization',
            SearchAnalyzerConfigConfig::FILTER_TYPE_STOPWORDS => 'sac_stopwords',
            SearchAnalyzerConfigConfig::FILTER_TYPE_DECOMPOUND => 'sac_decompound',
            default => '',
        };
    }

    /**
     * @param string $filterType One of SearchAnalyzerConfigConfig::FILTER_TYPES.
     */
    protected function resolveFilterLabel(string $filterType): string
    {
        return match ($filterType) {
            SearchAnalyzerConfigConfig::FILTER_TYPE_STEMMER => 'Stemmer',
            SearchAnalyzerConfigConfig::FILTER_TYPE_NORMALIZATION => 'Normalization',
            SearchAnalyzerConfigConfig::FILTER_TYPE_STOPWORDS => 'Stopwords',
            SearchAnalyzerConfigConfig::FILTER_TYPE_DECOMPOUND => 'Decompounding',
            default => $filterType,
        };
    }

    /**
     * collectMissingSlotWarnings() reports every active field at once -- a small single-filter page only
     * wants the warning(s) about ITS OWN slot, not an unrelated field's already-acknowledged warning
     * resurfacing here. Every warning message names its slot literally (see
     * SearchAnalyzerConfigRenderer::applySlot()), so a substring match is exact, not a guess.
     *
     * @param array<string> $warnings
     * @param string $slotName
     *
     * @return array<string>
     */
    protected function filterWarningsBySlot(array $warnings, string $slotName): array
    {
        return array_values(array_filter(
            $warnings,
            static fn (string $warning): bool => str_contains($warning, $slotName),
        ));
    }

    /**
     * One row per slot (SearchAnalyzerConfigRenderer::CHAIN_VISIBLE_SLOT_NAMES_IN_RECOMMENDED_ORDER, minus
     * the never-chain-visible inner decompound filter) for the overview page's matrix: a human-readable
     * current value plus the one "Edit" link for that row -- the four scalar filters go to their own small
     * page, the two list-only ones (sac_keyword_marker, sac_synonyms) go straight to their list page since
     * neither has a scalar value of its own. `edited` comes straight from $editedSlots (see
     * SearchAnalyzerConfigFacadeInterface::describeEditedSlots() -- a real live-cluster diff, not a
     * revision-history one).
     *
     * @param \Generated\Shared\Transfer\SearchAnalyzerConfigTransfer $searchAnalyzerConfigTransfer
     * @param array<string, bool> $editedSlots
     * @param string $sourceIdentifier
     * @param string $storeName
     *
     * @return array<array{slot: string, label: string, summary: string, editUrl: string, edited: bool}>
     */
    protected function buildFilterRows(
        SearchAnalyzerConfigTransfer $searchAnalyzerConfigTransfer,
        array $editedSlots,
        string $sourceIdentifier,
        string $storeName,
    ): array {
        $config = $this->getFactory()->getConfig();
        $stemmerLanguageLabels = $config->getAllowedStemmerLanguages();
        $normalizationFilterLabels = $config->getAllowedNormalizationFilters();
        $stopwordsBuiltinLanguageLabels = $config->getAllowedBuiltinStopwordsLanguages();

        $stemmerLanguage = $searchAnalyzerConfigTransfer->getStemmerLanguage() ?? '';
        $normalizationFilter = $searchAnalyzerConfigTransfer->getNormalizationFilter() ?? '';
        $stopwordsMode = $searchAnalyzerConfigTransfer->getStopwordsMode() ?? SearchAnalyzerConfigConfig::STOPWORDS_MODE_NONE;
        $decompoundEnabled = (bool)$searchAnalyzerConfigTransfer->getDecompoundEnabled();

        $stopwordsSummary = match ($stopwordsMode) {
            SearchAnalyzerConfigConfig::STOPWORDS_MODE_BUILTIN => 'Built-in: ' . ($stopwordsBuiltinLanguageLabels[$searchAnalyzerConfigTransfer->getStopwordsBuiltinLanguage() ?? ''] ?? 'None selected'),
            SearchAnalyzerConfigConfig::STOPWORDS_MODE_CUSTOM => count($this->getListTerms($searchAnalyzerConfigTransfer, SearchAnalyzerConfigConfig::LIST_TYPE_STOPWORD)) . ' custom word(s) staged',
            default => 'None',
        };

        $decompoundSummary = $decompoundEnabled
            ? 'Enabled (' . count($this->getListTerms($searchAnalyzerConfigTransfer, SearchAnalyzerConfigConfig::LIST_TYPE_DECOMPOUND_WORD)) . ' word(s) staged)'
            : 'Disabled';

        return [
            [
                'slot' => 'sac_stemmer',
                'label' => 'Stemmer',
                'summary' => $stemmerLanguageLabels[$stemmerLanguage] ?? 'None',
                'editUrl' => $this->buildEditFilterUrl($sourceIdentifier, $storeName, SearchAnalyzerConfigConfig::FILTER_TYPE_STEMMER),
                'edited' => $editedSlots['sac_stemmer'] ?? false,
            ],
            [
                'slot' => 'sac_normalization',
                'label' => 'Normalization',
                'summary' => $normalizationFilterLabels[$normalizationFilter] ?? 'None',
                'editUrl' => $this->buildEditFilterUrl($sourceIdentifier, $storeName, SearchAnalyzerConfigConfig::FILTER_TYPE_NORMALIZATION),
                'edited' => $editedSlots['sac_normalization'] ?? false,
            ],
            [
                'slot' => 'sac_stopwords',
                'label' => 'Stopwords',
                'summary' => $stopwordsSummary,
                'editUrl' => $this->buildEditFilterUrl($sourceIdentifier, $storeName, SearchAnalyzerConfigConfig::FILTER_TYPE_STOPWORDS),
                'edited' => $editedSlots['sac_stopwords'] ?? false,
            ],
            [
                'slot' => 'sac_decompound',
                'label' => 'Decompounding',
                'summary' => $decompoundSummary,
                'editUrl' => $this->buildEditFilterUrl($sourceIdentifier, $storeName, SearchAnalyzerConfigConfig::FILTER_TYPE_DECOMPOUND),
                'edited' => $editedSlots['sac_decompound'] ?? false,
            ],
            [
                'slot' => 'sac_keyword_marker',
                'label' => 'Do-not-decompound / brand list',
                'summary' => count($this->getListTerms($searchAnalyzerConfigTransfer, SearchAnalyzerConfigConfig::LIST_TYPE_DO_NOT_DECOMPOUND)) . ' term(s) staged',
                'editUrl' => $this->buildEditListUrl($sourceIdentifier, $storeName, SearchAnalyzerConfigConfig::LIST_TYPE_DO_NOT_DECOMPOUND),
                'edited' => $editedSlots['sac_keyword_marker'] ?? false,
            ],
            [
                'slot' => 'sac_synonyms',
                'label' => 'Synonyms',
                'summary' => count($this->getListTerms($searchAnalyzerConfigTransfer, SearchAnalyzerConfigConfig::LIST_TYPE_SYNONYM)) . ' rule(s) staged',
                'editUrl' => $this->buildEditListUrl($sourceIdentifier, $storeName, SearchAnalyzerConfigConfig::LIST_TYPE_SYNONYM),
                'edited' => $editedSlots['sac_synonyms'] ?? false,
            ],
        ];
    }

    /**
     * @param \Generated\Shared\Transfer\SearchAnalyzerConfigTransfer $searchAnalyzerConfigTransfer
     *
     * @return array<string, mixed>
     */
    protected function transferToFormData(SearchAnalyzerConfigTransfer $searchAnalyzerConfigTransfer, ?string $listType = null): array
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

    /**
     * Only the field(s) in $fields are overwritten from $formData -- every other field (including the
     * term lists that AREN'T $listType, which are never on this form at all) is carried over unchanged
     * from $existingSearchAnalyzerConfigTransfer, so submitting one filter's small page can never blank
     * out another filter's value.
     *
     * @param array<string, mixed> $formData
     * @param \Generated\Shared\Transfer\SearchAnalyzerConfigTransfer $existingSearchAnalyzerConfigTransfer
     * @param string $sourceIdentifier
     * @param string $storeName
     * @param array<string> $fields Which SearchAnalyzerConfigEditForm::FIELD_* were actually on the submitted form.
     * @param string|null $listType The one list type (if any) FIELD_LIST_TEXT maps to for this filter -- see resolveFilterListType().
     */
    protected function formDataToTransfer(
        array $formData,
        SearchAnalyzerConfigTransfer $existingSearchAnalyzerConfigTransfer,
        string $sourceIdentifier,
        string $storeName,
        array $fields,
        ?string $listType = null,
    ): SearchAnalyzerConfigTransfer {
        $searchAnalyzerConfigTransfer = (clone $existingSearchAnalyzerConfigTransfer)
            ->setSourceIdentifier($sourceIdentifier)
            ->setStoreName($storeName);

        if (in_array(SearchAnalyzerConfigEditForm::FIELD_STEMMER_LANGUAGE, $fields, true)) {
            $searchAnalyzerConfigTransfer->setStemmerLanguage($this->nullIfEmpty((string)$formData[SearchAnalyzerConfigEditForm::FIELD_STEMMER_LANGUAGE]));
        }

        if (in_array(SearchAnalyzerConfigEditForm::FIELD_NORMALIZATION_FILTER, $fields, true)) {
            $searchAnalyzerConfigTransfer->setNormalizationFilter($this->nullIfEmpty((string)$formData[SearchAnalyzerConfigEditForm::FIELD_NORMALIZATION_FILTER]));
        }

        if (in_array(SearchAnalyzerConfigEditForm::FIELD_STOPWORDS_MODE, $fields, true)) {
            $searchAnalyzerConfigTransfer->setStopwordsMode((string)$formData[SearchAnalyzerConfigEditForm::FIELD_STOPWORDS_MODE]);
        }

        if (in_array(SearchAnalyzerConfigEditForm::FIELD_STOPWORDS_BUILTIN_LANGUAGE, $fields, true)) {
            $searchAnalyzerConfigTransfer->setStopwordsBuiltinLanguage($this->nullIfEmpty((string)$formData[SearchAnalyzerConfigEditForm::FIELD_STOPWORDS_BUILTIN_LANGUAGE]));
        }

        if (in_array(SearchAnalyzerConfigEditForm::FIELD_DECOMPOUND_ENABLED, $fields, true)) {
            $searchAnalyzerConfigTransfer->setDecompoundEnabled((bool)$formData[SearchAnalyzerConfigEditForm::FIELD_DECOMPOUND_ENABLED]);
        }

        if (in_array(SearchAnalyzerConfigEditForm::FIELD_LIST_TEXT, $fields, true) && $listType !== null) {
            $this->setListTerms($searchAnalyzerConfigTransfer, $listType, $this->textToTerms((string)$formData[SearchAnalyzerConfigEditForm::FIELD_LIST_TEXT], $listType));
        }

        return $searchAnalyzerConfigTransfer;
    }

    /**
     * @param \Generated\Shared\Transfer\SearchAnalyzerConfigTransfer $searchAnalyzerConfigTransfer
     * @param string $listType One of SearchAnalyzerConfigConfig::LIST_TYPES.
     *
     * @return \ArrayObject<int, \Generated\Shared\Transfer\SearchAnalyzerConfigTermTransfer>
     */
    protected function getListTerms(SearchAnalyzerConfigTransfer $searchAnalyzerConfigTransfer, string $listType): ArrayObject
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
    protected function setListTerms(SearchAnalyzerConfigTransfer $searchAnalyzerConfigTransfer, string $listType, ArrayObject $terms): void
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
