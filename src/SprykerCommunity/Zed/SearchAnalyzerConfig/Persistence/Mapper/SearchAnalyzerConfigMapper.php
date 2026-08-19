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

class SearchAnalyzerConfigMapper implements SearchAnalyzerConfigMapperInterface
{
    /**
     * @param \Orm\Zed\SearchAnalyzerConfig\Persistence\SpySearchAnalyzerConfig $spySearchAnalyzerConfig
     * @param \Generated\Shared\Transfer\SearchAnalyzerConfigTransfer $searchAnalyzerConfigTransfer
     */
    public function mapEntityToTransfer(
        SpySearchAnalyzerConfig $spySearchAnalyzerConfig,
        SearchAnalyzerConfigTransfer $searchAnalyzerConfigTransfer,
    ): SearchAnalyzerConfigTransfer {
        return $searchAnalyzerConfigTransfer
            ->setIdSearchAnalyzerConfig($spySearchAnalyzerConfig->getIdSearchAnalyzerConfig())
            ->setSourceIdentifier($spySearchAnalyzerConfig->getSourceIdentifier())
            ->setStoreName($spySearchAnalyzerConfig->getStoreName())
            ->setStemmerLanguage($spySearchAnalyzerConfig->getStemmerLanguage())
            ->setNormalizationFilter($spySearchAnalyzerConfig->getNormalizationFilter())
            ->setStopwordsMode($spySearchAnalyzerConfig->getStopwordsMode())
            ->setStopwordsBuiltinLanguage($spySearchAnalyzerConfig->getStopwordsBuiltinLanguage())
            ->setDecompoundEnabled($spySearchAnalyzerConfig->getDecompoundEnabled())
            ->setRevision($spySearchAnalyzerConfig->getRevision())
            ->setAppliedRevision($spySearchAnalyzerConfig->getAppliedRevision())
            ->setAppliedIndexName($spySearchAnalyzerConfig->getAppliedIndexName())
            ->setAppliedAt($spySearchAnalyzerConfig->getAppliedAt() ? $spySearchAnalyzerConfig->getAppliedAt()->format(DATE_ATOM) : null);
    }

    /**
     * @param \Orm\Zed\SearchAnalyzerConfig\Persistence\SpySearchAnalyzerConfigTerm $spySearchAnalyzerConfigTerm
     * @param \Generated\Shared\Transfer\SearchAnalyzerConfigTermTransfer $searchAnalyzerConfigTermTransfer
     */
    public function mapTermEntityToTransfer(
        SpySearchAnalyzerConfigTerm $spySearchAnalyzerConfigTerm,
        SearchAnalyzerConfigTermTransfer $searchAnalyzerConfigTermTransfer,
    ): SearchAnalyzerConfigTermTransfer {
        return $searchAnalyzerConfigTermTransfer
            ->setIdSearchAnalyzerConfigTerm($spySearchAnalyzerConfigTerm->getIdSearchAnalyzerConfigTerm())
            ->setListType($spySearchAnalyzerConfigTerm->getListType())
            ->setTerm($spySearchAnalyzerConfigTerm->getTerm())
            ->setSortOrder($spySearchAnalyzerConfigTerm->getSortOrder());
    }
}
