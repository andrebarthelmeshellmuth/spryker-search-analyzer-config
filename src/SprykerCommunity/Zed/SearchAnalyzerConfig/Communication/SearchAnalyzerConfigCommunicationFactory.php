<?php

/**
 * This file is part of the spryker-community/search-analyzer-config package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchAnalyzerConfig\Communication;

use Spryker\Zed\Kernel\Communication\AbstractCommunicationFactory;
use SprykerCommunity\Shared\SearchAnalyzerConfig\SearchAnalyzerConfigConfig as SharedSearchAnalyzerConfigConfig;
use SprykerCommunity\Zed\SearchAnalyzerConfig\Communication\Form\SearchAnalyzerConfigCopyForm;
use SprykerCommunity\Zed\SearchAnalyzerConfig\Communication\Form\SearchAnalyzerConfigEditForm;
use SprykerCommunity\Zed\SearchAnalyzerConfig\Communication\Form\SearchAnalyzerConfigEditListForm;
use SprykerCommunity\Zed\SearchAnalyzerConfig\Communication\Form\SearchAnalyzerConfigPreviewForm;
use SprykerCommunity\Zed\SearchAnalyzerConfig\Communication\Form\SearchAnalyzerConfigRestoreForm;
use SprykerCommunity\Zed\SearchAnalyzerConfig\Communication\Form\SearchAnalyzerConfigScopeForm;
use SprykerCommunity\Zed\SearchAnalyzerConfig\Dependency\Facade\SearchAnalyzerConfigToSearchIndexAliasFacadeInterface;
use SprykerCommunity\Zed\SearchAnalyzerConfig\SearchAnalyzerConfigDependencyProvider;
use Symfony\Component\Form\FormInterface;

/**
 * @method \SprykerCommunity\Zed\SearchAnalyzerConfig\SearchAnalyzerConfigConfig getConfig()
 * @method \SprykerCommunity\Zed\SearchAnalyzerConfig\Business\SearchAnalyzerConfigFacadeInterface getFacade()
 */
class SearchAnalyzerConfigCommunicationFactory extends AbstractCommunicationFactory
{
    /**
     * @param string $sourceIdentifier
     * @param string $storeName
     * @param string $redirectTo
     */
    public function createSearchAnalyzerConfigScopeForm(string $sourceIdentifier, string $storeName, string $redirectTo = ''): FormInterface
    {
        return $this->getFormFactory()->create(SearchAnalyzerConfigScopeForm::class, [
            SearchAnalyzerConfigScopeForm::FIELD_SOURCE_IDENTIFIER => $sourceIdentifier,
            SearchAnalyzerConfigScopeForm::FIELD_STORE_NAME => $storeName,
            SearchAnalyzerConfigScopeForm::FIELD_REDIRECT_TO => $redirectTo,
        ]);
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string> $fields Which of SearchAnalyzerConfigEditForm::FIELD_* to build -- see
     *  ConfigController::resolveFilterFields() for the FILTER_TYPE => fields mapping.
     * @param string $listTextLabel Only used when $fields includes SearchAnalyzerConfigEditForm::FIELD_LIST_TEXT.
     */
    public function createSearchAnalyzerConfigEditForm(array $data, array $fields, string $listTextLabel = 'Terms (one per line)'): FormInterface
    {
        return $this->getFormFactory()->create(SearchAnalyzerConfigEditForm::class, $data, [
            SearchAnalyzerConfigEditForm::OPTION_STEMMER_LANGUAGE_CHOICES => $this->getConfig()->getAllowedStemmerLanguages(),
            SearchAnalyzerConfigEditForm::OPTION_NORMALIZATION_FILTER_CHOICES => $this->getConfig()->getAllowedNormalizationFilters(),
            SearchAnalyzerConfigEditForm::OPTION_STOPWORDS_BUILTIN_LANGUAGE_CHOICES => $this->getConfig()->getAllowedBuiltinStopwordsLanguages(),
            SearchAnalyzerConfigEditForm::OPTION_LIST_TEXT_LABEL => $listTextLabel,
            SearchAnalyzerConfigEditForm::OPTION_FIELDS => $fields,
        ]);
    }

    /**
     * @param string $sourceIdentifier
     * @param string $storeName
     * @param string $listType One of SprykerCommunity\Shared\SearchAnalyzerConfig\SearchAnalyzerConfigConfig::LIST_TYPES.
     * @param string $text
     */
    public function createSearchAnalyzerConfigEditListForm(string $sourceIdentifier, string $storeName, string $listType, string $text): FormInterface
    {
        return $this->getFormFactory()->create(SearchAnalyzerConfigEditListForm::class, [
            SearchAnalyzerConfigEditListForm::FIELD_SOURCE_IDENTIFIER => $sourceIdentifier,
            SearchAnalyzerConfigEditListForm::FIELD_STORE_NAME => $storeName,
            SearchAnalyzerConfigEditListForm::FIELD_LIST_TYPE => $listType,
            SearchAnalyzerConfigEditListForm::FIELD_TEXT => $text,
        ], [
            SearchAnalyzerConfigEditListForm::OPTION_LABEL => $this->getEditListLabel($listType),
        ]);
    }

    /**
     * Also used directly by ConfigController::editFilterAction() -- the stopwords/decompound filter pages
     * embed their own list's textarea inline (see SearchAnalyzerConfigEditForm::FIELD_LIST_TEXT) and want
     * the exact same label text a standalone list-edit page would use.
     *
     * @param string $listType One of SprykerCommunity\Shared\SearchAnalyzerConfig\SearchAnalyzerConfigConfig::LIST_TYPES.
     */
    public function getEditListLabel(string $listType): string
    {
        return match ($listType) {
            SharedSearchAnalyzerConfigConfig::LIST_TYPE_SYNONYM => 'Synonyms, Solr-style, one rule per line (equivalent: "sofa, couch"; directional: "tv => television")',
            SharedSearchAnalyzerConfigConfig::LIST_TYPE_DECOMPOUND_WORD => 'Decompound word list (one per line)',
            SharedSearchAnalyzerConfigConfig::LIST_TYPE_STOPWORD => 'Custom stopwords (one per line)',
            SharedSearchAnalyzerConfigConfig::LIST_TYPE_DO_NOT_DECOMPOUND => 'Do-not-decompound / brand terms (one per line) -- restricted to letters, digits, "_", "-", "." (rendered into a script, not just a word list)',
            default => 'Terms (one per line)',
        };
    }

    /**
     * @param string $sourceIdentifier
     * @param string $storeName
     */
    public function createSearchAnalyzerConfigCopyForm(string $sourceIdentifier, string $storeName): FormInterface
    {
        return $this->getFormFactory()->create(SearchAnalyzerConfigCopyForm::class, [
            SearchAnalyzerConfigCopyForm::FIELD_SOURCE_IDENTIFIER => $sourceIdentifier,
            SearchAnalyzerConfigCopyForm::FIELD_STORE_NAME => $storeName,
        ]);
    }

    /**
     * @param string $sourceIdentifier
     * @param string $storeName
     * @param int $revision
     * @param string $redirectTo Empty (the default) means "redirect back to History" -- see the form's own doc block.
     */
    public function createSearchAnalyzerConfigRestoreForm(string $sourceIdentifier, string $storeName, int $revision, string $redirectTo = ''): FormInterface
    {
        return $this->getFormFactory()->create(SearchAnalyzerConfigRestoreForm::class, [
            SearchAnalyzerConfigRestoreForm::FIELD_SOURCE_IDENTIFIER => $sourceIdentifier,
            SearchAnalyzerConfigRestoreForm::FIELD_STORE_NAME => $storeName,
            SearchAnalyzerConfigRestoreForm::FIELD_REVISION => $revision,
            SearchAnalyzerConfigRestoreForm::FIELD_REDIRECT_TO => $redirectTo,
        ]);
    }

    /**
     * @param string $sourceIdentifier
     * @param string $storeName
     * @param array<string> $targetAnalyzerNameChoices Live-discovered via SearchAnalyzerConfigFacadeInterface::getManagedAnalyzerNames() -- see that method's own doc block.
     */
    public function createSearchAnalyzerConfigPreviewForm(string $sourceIdentifier, string $storeName, array $targetAnalyzerNameChoices): FormInterface
    {
        return $this->getFormFactory()->create(SearchAnalyzerConfigPreviewForm::class, [
            SearchAnalyzerConfigPreviewForm::FIELD_SOURCE_IDENTIFIER => $sourceIdentifier,
            SearchAnalyzerConfigPreviewForm::FIELD_STORE_NAME => $storeName,
        ], [
            SearchAnalyzerConfigPreviewForm::OPTION_TARGET_ANALYZER_NAME_CHOICES => $targetAnalyzerNameChoices,
        ]);
    }

    public function getSearchIndexAliasFacade(): SearchAnalyzerConfigToSearchIndexAliasFacadeInterface
    {
        return $this->getProvidedDependency(SearchAnalyzerConfigDependencyProvider::FACADE_SEARCH_INDEX_ALIAS);
    }
}
