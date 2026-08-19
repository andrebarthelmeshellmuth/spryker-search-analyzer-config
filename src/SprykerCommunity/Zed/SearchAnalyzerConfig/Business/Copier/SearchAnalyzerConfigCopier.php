<?php

/**
 * This file is part of the spryker-community/search-analyzer-config package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchAnalyzerConfig\Business\Copier;

use ArrayObject;
use Generated\Shared\Transfer\SearchAnalyzerConfigTermTransfer;
use Generated\Shared\Transfer\SearchAnalyzerConfigTransfer;

class SearchAnalyzerConfigCopier implements SearchAnalyzerConfigCopierInterface
{
    /**
     * @param \Generated\Shared\Transfer\SearchAnalyzerConfigTransfer $sourceSearchAnalyzerConfigTransfer
     * @param string $targetSourceIdentifier
     * @param string $targetStoreName
     */
    public function copy(
        SearchAnalyzerConfigTransfer $sourceSearchAnalyzerConfigTransfer,
        string $targetSourceIdentifier,
        string $targetStoreName,
    ): SearchAnalyzerConfigTransfer {
        return (new SearchAnalyzerConfigTransfer())
            ->setSourceIdentifier($targetSourceIdentifier)
            ->setStoreName($targetStoreName)
            ->setStemmerLanguage($sourceSearchAnalyzerConfigTransfer->getStemmerLanguage())
            ->setNormalizationFilter($sourceSearchAnalyzerConfigTransfer->getNormalizationFilter())
            ->setStopwordsMode($sourceSearchAnalyzerConfigTransfer->getStopwordsMode())
            ->setStopwordsBuiltinLanguage($sourceSearchAnalyzerConfigTransfer->getStopwordsBuiltinLanguage())
            ->setDecompoundEnabled($sourceSearchAnalyzerConfigTransfer->getDecompoundEnabled())
            ->setDecompoundWords($this->copyTerms($sourceSearchAnalyzerConfigTransfer->getDecompoundWords()))
            ->setSynonyms($this->copyTerms($sourceSearchAnalyzerConfigTransfer->getSynonyms()))
            ->setStopwords($this->copyTerms($sourceSearchAnalyzerConfigTransfer->getStopwords()))
            ->setDoNotDecompoundTerms($this->copyTerms($sourceSearchAnalyzerConfigTransfer->getDoNotDecompoundTerms()));
    }

    /**
     * Strips idSearchAnalyzerConfigTerm -- a copied term is a new row in the target scope, not a
     * relocation of the source scope's own row.
     *
     * @param \ArrayObject<int, \Generated\Shared\Transfer\SearchAnalyzerConfigTermTransfer> $searchAnalyzerConfigTermTransfers
     *
     * @return \ArrayObject<int, \Generated\Shared\Transfer\SearchAnalyzerConfigTermTransfer>
     */
    protected function copyTerms(ArrayObject $searchAnalyzerConfigTermTransfers): ArrayObject
    {
        $copies = [];

        foreach ($searchAnalyzerConfigTermTransfers as $searchAnalyzerConfigTermTransfer) {
            /** @var \Generated\Shared\Transfer\SearchAnalyzerConfigTermTransfer $searchAnalyzerConfigTermTransfer */
            $copies[] = (new SearchAnalyzerConfigTermTransfer())
                ->setListType($searchAnalyzerConfigTermTransfer->getListType())
                ->setTerm($searchAnalyzerConfigTermTransfer->getTerm())
                ->setSortOrder($searchAnalyzerConfigTermTransfer->getSortOrder());
        }

        return new ArrayObject($copies);
    }
}
