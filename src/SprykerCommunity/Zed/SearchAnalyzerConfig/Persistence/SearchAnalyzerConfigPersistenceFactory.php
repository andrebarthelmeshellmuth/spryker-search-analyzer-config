<?php

/**
 * This file is part of the spryker-community/search-analyzer-config package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchAnalyzerConfig\Persistence;

use Orm\Zed\SearchAnalyzerConfig\Persistence\SpySearchAnalyzerConfigQuery;
use Orm\Zed\SearchAnalyzerConfig\Persistence\SpySearchAnalyzerConfigRevisionQuery;
use Orm\Zed\SearchAnalyzerConfig\Persistence\SpySearchAnalyzerConfigTermQuery;
use Spryker\Zed\Kernel\Persistence\AbstractPersistenceFactory;
use SprykerCommunity\Zed\SearchAnalyzerConfig\Persistence\Mapper\SearchAnalyzerConfigMapper;
use SprykerCommunity\Zed\SearchAnalyzerConfig\Persistence\Mapper\SearchAnalyzerConfigMapperInterface;

class SearchAnalyzerConfigPersistenceFactory extends AbstractPersistenceFactory
{
    public function createSpySearchAnalyzerConfigQuery(): SpySearchAnalyzerConfigQuery
    {
        return SpySearchAnalyzerConfigQuery::create();
    }

    public function createSpySearchAnalyzerConfigTermQuery(): SpySearchAnalyzerConfigTermQuery
    {
        return SpySearchAnalyzerConfigTermQuery::create();
    }

    public function createSpySearchAnalyzerConfigRevisionQuery(): SpySearchAnalyzerConfigRevisionQuery
    {
        return SpySearchAnalyzerConfigRevisionQuery::create();
    }

    public function createSearchAnalyzerConfigMapper(): SearchAnalyzerConfigMapperInterface
    {
        return new SearchAnalyzerConfigMapper();
    }
}
