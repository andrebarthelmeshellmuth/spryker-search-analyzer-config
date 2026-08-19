<?php

/**
 * This file is part of the spryker-community/search-analyzer-config package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchAnalyzerConfig;

use Spryker\Zed\Kernel\AbstractBundleDependencyProvider;
use Spryker\Zed\Kernel\Container;
use SprykerCommunity\Zed\SearchAnalyzerConfig\Dependency\Facade\SearchAnalyzerConfigToSearchIndexAliasFacadeBridge;

class SearchAnalyzerConfigDependencyProvider extends AbstractBundleDependencyProvider
{
    /**
     * @var string
     */
    public const FACADE_SEARCH_INDEX_ALIAS = 'FACADE_SEARCH_INDEX_ALIAS';

    /**
     * @param \Spryker\Zed\Kernel\Container $container
     */
    public function provideBusinessLayerDependencies(Container $container): Container
    {
        $container = parent::provideBusinessLayerDependencies($container);

        return $this->addSearchIndexAliasFacade($container);
    }

    /**
     * @param \Spryker\Zed\Kernel\Container $container
     */
    public function provideCommunicationLayerDependencies(Container $container): Container
    {
        $container = parent::provideCommunicationLayerDependencies($container);

        return $this->addSearchIndexAliasFacade($container);
    }

    /**
     * @param \Spryker\Zed\Kernel\Container $container
     */
    protected function addSearchIndexAliasFacade(Container $container): Container
    {
        $container->set(static::FACADE_SEARCH_INDEX_ALIAS, fn (Container $container): SearchAnalyzerConfigToSearchIndexAliasFacadeBridge => new SearchAnalyzerConfigToSearchIndexAliasFacadeBridge($container->getLocator()->searchIndexAlias()->facade()));

        return $container;
    }
}
