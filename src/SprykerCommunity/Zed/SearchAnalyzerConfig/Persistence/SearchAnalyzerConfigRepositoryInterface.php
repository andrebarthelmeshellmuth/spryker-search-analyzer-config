<?php

/**
 * This file is part of the spryker-community/search-analyzer-config package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchAnalyzerConfig\Persistence;

use Generated\Shared\Transfer\SearchAnalyzerConfigTransfer;

interface SearchAnalyzerConfigRepositoryInterface
{
    /**
     * @param string $sourceIdentifier
     * @param string $storeName
     */
    public function findByScope(string $sourceIdentifier, string $storeName): ?SearchAnalyzerConfigTransfer;

    /**
     * @param string $sourceIdentifier
     * @param string $storeName
     *
     * @return array<\Generated\Shared\Transfer\SearchAnalyzerConfigRevisionTransfer>
     */
    public function getRevisionHistory(string $sourceIdentifier, string $storeName): array;
}
