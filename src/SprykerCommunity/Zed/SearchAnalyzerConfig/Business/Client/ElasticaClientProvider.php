<?php

/**
 * This file is part of the spryker-community/search-analyzer-config package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchAnalyzerConfig\Business\Client;

use Elastica\Client;
use Spryker\Shared\SearchElasticsearch\ElasticaClient\ElasticaClientFactory;
use Spryker\Zed\SearchElasticsearch\SearchElasticsearchConfig;

/**
 * Same Zed-native connection path as `spryker-community/search-index-alias`'s own
 * `ElasticaClientProvider` (copied rather than shared: this package and search-index-alias must each
 * stay independently installable, so no cross-package code dependency):
 * `Spryker\Shared\SearchElasticsearch\ElasticaClient\ElasticaClientFactory` is explicitly a Shared-layer
 * class meant for reuse, distinct from the Client-layer `Client\Search`/`Client\Catalog` facades that
 * crash when called from Zed/console: their query-expander plugins unconditionally call
 * `Client\Store::getCurrentStore()`/`Client\Locale::getCurrentLocale()`, which have no current
 * Store/Locale context to resolve outside a live Yves/Glue request (not literally "no HTTP session" --
 * a Zed controller request has one and still crashes the same way).
 * `SearchAnalyzerConfigPreviewer` uses this to run `_analyze` against both the live alias (read-only) and
 * a throwaway index (created and torn down within the same request).
 */
class ElasticaClientProvider implements ElasticaClientProviderInterface
{
    /**
     * @param \Spryker\Zed\SearchElasticsearch\SearchElasticsearchConfig $searchElasticsearchConfig
     */
    public function __construct(protected SearchElasticsearchConfig $searchElasticsearchConfig)
    {
    }

    public function getClient(): Client
    {
        return (new ElasticaClientFactory())->createClient(
            $this->searchElasticsearchConfig->getClientConfig(),
        );
    }
}
