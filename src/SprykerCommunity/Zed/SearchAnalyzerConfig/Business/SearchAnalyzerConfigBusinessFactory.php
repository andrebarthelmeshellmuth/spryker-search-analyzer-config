<?php

/**
 * This file is part of the spryker-community/search-analyzer-config package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchAnalyzerConfig\Business;

use Spryker\Zed\Kernel\Business\AbstractBusinessFactory;
use Spryker\Zed\SearchElasticsearch\SearchElasticsearchConfig;
use SprykerCommunity\Zed\SearchAnalyzerConfig\Business\Client\ElasticaClientProvider;
use SprykerCommunity\Zed\SearchAnalyzerConfig\Business\Client\ElasticaClientProviderInterface;
use SprykerCommunity\Zed\SearchAnalyzerConfig\Business\Copier\SearchAnalyzerConfigCopier;
use SprykerCommunity\Zed\SearchAnalyzerConfig\Business\Copier\SearchAnalyzerConfigCopierInterface;
use SprykerCommunity\Zed\SearchAnalyzerConfig\Business\Previewer\SearchAnalyzerConfigPreviewer;
use SprykerCommunity\Zed\SearchAnalyzerConfig\Business\Previewer\SearchAnalyzerConfigPreviewerInterface;
use SprykerCommunity\Zed\SearchAnalyzerConfig\Business\Renderer\SearchAnalyzerConfigRenderer;
use SprykerCommunity\Zed\SearchAnalyzerConfig\Business\Renderer\SearchAnalyzerConfigRendererInterface;
use SprykerCommunity\Zed\SearchAnalyzerConfig\Business\Validator\SearchAnalyzerConfigValidator;
use SprykerCommunity\Zed\SearchAnalyzerConfig\Business\Validator\SearchAnalyzerConfigValidatorInterface;
use SprykerCommunity\Zed\SearchAnalyzerConfig\Dependency\Facade\SearchAnalyzerConfigToSearchIndexAliasFacadeInterface;
use SprykerCommunity\Zed\SearchAnalyzerConfig\SearchAnalyzerConfigDependencyProvider;

/**
 * @method \SprykerCommunity\Zed\SearchAnalyzerConfig\SearchAnalyzerConfigConfig getConfig()
 * @method \SprykerCommunity\Zed\SearchAnalyzerConfig\Persistence\SearchAnalyzerConfigRepositoryInterface getRepository()
 */
class SearchAnalyzerConfigBusinessFactory extends AbstractBusinessFactory
{
    public function createSearchAnalyzerConfigRenderer(): SearchAnalyzerConfigRendererInterface
    {
        return new SearchAnalyzerConfigRenderer();
    }

    public function createSearchAnalyzerConfigValidator(): SearchAnalyzerConfigValidatorInterface
    {
        return new SearchAnalyzerConfigValidator();
    }

    public function createSearchAnalyzerConfigCopier(): SearchAnalyzerConfigCopierInterface
    {
        return new SearchAnalyzerConfigCopier();
    }

    public function createSearchAnalyzerConfigPreviewer(): SearchAnalyzerConfigPreviewerInterface
    {
        return new SearchAnalyzerConfigPreviewer(
            $this->getRepository(),
            $this->createSearchAnalyzerConfigRenderer(),
            $this->getConfig(),
            $this->getSearchIndexAliasFacade(),
            $this->createElasticaClientProvider(),
        );
    }

    public function createElasticaClientProvider(): ElasticaClientProviderInterface
    {
        return new ElasticaClientProvider($this->createSearchElasticsearchConfig());
    }

    protected function createSearchElasticsearchConfig(): SearchElasticsearchConfig
    {
        return new SearchElasticsearchConfig();
    }

    public function getSearchIndexAliasFacade(): SearchAnalyzerConfigToSearchIndexAliasFacadeInterface
    {
        return $this->getProvidedDependency(SearchAnalyzerConfigDependencyProvider::FACADE_SEARCH_INDEX_ALIAS);
    }
}
