<?php

/**
 * This file is part of the spryker-community/search-analyzer-config package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchAnalyzerConfig\Persistence\Mapper;

use Generated\Shared\Transfer\SearchAnalyzerConfigTermTransfer;
use Generated\Shared\Transfer\SearchAnalyzerConfigTransfer;
use Orm\Zed\SearchAnalyzerConfig\Persistence\SpySearchAnalyzerConfig;
use Orm\Zed\SearchAnalyzerConfig\Persistence\SpySearchAnalyzerConfigTerm;

interface SearchAnalyzerConfigMapperInterface
{
    /**
     * Maps only the scalar/bookkeeping columns -- term lists are a separate query, assembled by the
     * repository (see that class's own doc block for why).
     *
     * @param \Orm\Zed\SearchAnalyzerConfig\Persistence\SpySearchAnalyzerConfig $spySearchAnalyzerConfig
     * @param \Generated\Shared\Transfer\SearchAnalyzerConfigTransfer $searchAnalyzerConfigTransfer
     */
    public function mapEntityToTransfer(
        SpySearchAnalyzerConfig $spySearchAnalyzerConfig,
        SearchAnalyzerConfigTransfer $searchAnalyzerConfigTransfer,
    ): SearchAnalyzerConfigTransfer;

    /**
     * @param \Orm\Zed\SearchAnalyzerConfig\Persistence\SpySearchAnalyzerConfigTerm $spySearchAnalyzerConfigTerm
     * @param \Generated\Shared\Transfer\SearchAnalyzerConfigTermTransfer $searchAnalyzerConfigTermTransfer
     */
    public function mapTermEntityToTransfer(
        SpySearchAnalyzerConfigTerm $spySearchAnalyzerConfigTerm,
        SearchAnalyzerConfigTermTransfer $searchAnalyzerConfigTermTransfer,
    ): SearchAnalyzerConfigTermTransfer;
}
