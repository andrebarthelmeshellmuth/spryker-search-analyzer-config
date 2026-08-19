<?php

/**
 * This file is part of the spryker-community/search-analyzer-config package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchAnalyzerConfig\Communication;

use Spryker\Zed\Kernel\Communication\AbstractCommunicationFactory;
use SprykerCommunity\Zed\SearchAnalyzerConfig\Communication\Form\SearchAnalyzerConfigCopyForm;
use SprykerCommunity\Zed\SearchAnalyzerConfig\Communication\Form\SearchAnalyzerConfigEditForm;
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
     */
    public function createSearchAnalyzerConfigEditForm(array $data): FormInterface
    {
        return $this->getFormFactory()->create(SearchAnalyzerConfigEditForm::class, $data, [
            SearchAnalyzerConfigEditForm::OPTION_STEMMER_LANGUAGE_CHOICES => $this->getConfig()->getAllowedStemmerLanguages(),
            SearchAnalyzerConfigEditForm::OPTION_NORMALIZATION_FILTER_CHOICES => $this->getConfig()->getAllowedNormalizationFilters(),
            SearchAnalyzerConfigEditForm::OPTION_STOPWORDS_BUILTIN_LANGUAGE_CHOICES => $this->getConfig()->getAllowedBuiltinStopwordsLanguages(),
        ]);
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
     */
    public function createSearchAnalyzerConfigRestoreForm(string $sourceIdentifier, string $storeName, int $revision): FormInterface
    {
        return $this->getFormFactory()->create(SearchAnalyzerConfigRestoreForm::class, [
            SearchAnalyzerConfigRestoreForm::FIELD_SOURCE_IDENTIFIER => $sourceIdentifier,
            SearchAnalyzerConfigRestoreForm::FIELD_STORE_NAME => $storeName,
            SearchAnalyzerConfigRestoreForm::FIELD_REVISION => $revision,
        ]);
    }

    /**
     * @param string $sourceIdentifier
     * @param string $storeName
     */
    public function createSearchAnalyzerConfigPreviewForm(string $sourceIdentifier, string $storeName): FormInterface
    {
        return $this->getFormFactory()->create(SearchAnalyzerConfigPreviewForm::class, [
            SearchAnalyzerConfigPreviewForm::FIELD_SOURCE_IDENTIFIER => $sourceIdentifier,
            SearchAnalyzerConfigPreviewForm::FIELD_STORE_NAME => $storeName,
        ], [
            SearchAnalyzerConfigPreviewForm::OPTION_TARGET_ANALYZER_NAME_CHOICES => $this->getConfig()->getTargetAnalyzerNames(),
        ]);
    }

    public function getSearchIndexAliasFacade(): SearchAnalyzerConfigToSearchIndexAliasFacadeInterface
    {
        return $this->getProvidedDependency(SearchAnalyzerConfigDependencyProvider::FACADE_SEARCH_INDEX_ALIAS);
    }
}
