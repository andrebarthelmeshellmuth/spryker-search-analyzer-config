<?php

/**
 * This file is part of the spryker-community/search-analyzer-config package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Zed\SearchAnalyzerConfig\Business;

use Codeception\Test\Unit;
use Generated\Shared\Transfer\SearchIndexScopeTransfer;
use SprykerCommunity\Shared\SearchAnalyzerConfig\SearchIndexManagedScopeMatcher;

/**
 * Auto-generated group annotations
 *
 * @group SprykerCommunityTest
 * @group Zed
 * @group SearchAnalyzerConfig
 * @group Business
 * @group SearchIndexManagedScopeMatcherTest
 * Add your own group annotations below this line
 * @group Portable
 */
class SearchIndexManagedScopeMatcherTest extends Unit
{
    public function testReturnsNullForAnEmptyList(): void
    {
        $result = SearchIndexManagedScopeMatcher::match([], 'page', 'DE');

        $this->assertNull($result);
    }

    public function testReturnsNullWhenNoScopeMatchesBothSourceAndStore(): void
    {
        $searchIndexScopeTransfers = [
            $this->buildScope('page', 'DE'),
            $this->buildScope('page', 'AT'),
        ];

        $result = SearchIndexManagedScopeMatcher::match($searchIndexScopeTransfers, 'page', 'CH');

        $this->assertNull($result);
    }

    public function testMatchingBothSourceAndStoreIsRequired(): void
    {
        $searchIndexScopeTransfers = [
            $this->buildScope('page', 'AT'),
            $this->buildScope('merchant', 'DE'),
        ];

        $result = SearchIndexManagedScopeMatcher::match($searchIndexScopeTransfers, 'page', 'DE');

        $this->assertNull($result);
    }

    public function testReturnsTheScopeWhenBothSourceAndStoreMatch(): void
    {
        $wanted = $this->buildScope('page', 'DE');
        $searchIndexScopeTransfers = [
            $this->buildScope('page', 'AT'),
            $wanted,
            $this->buildScope('merchant', 'DE'),
        ];

        $result = SearchIndexManagedScopeMatcher::match($searchIndexScopeTransfers, 'page', 'DE');

        $this->assertSame($wanted, $result);
    }

    protected function buildScope(string $sourceIdentifier, string $storeName): SearchIndexScopeTransfer
    {
        return (new SearchIndexScopeTransfer())
            ->setSourceIdentifier($sourceIdentifier)
            ->setStoreName($storeName);
    }
}
