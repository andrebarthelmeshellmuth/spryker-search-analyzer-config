<?php

/**
 * This file is part of the spryker-community/search-analyzer-config package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchAnalyzerConfig\Communication\Controller;

use Generated\Shared\Transfer\SearchIndexScopeTransfer;
use Spryker\Zed\Kernel\Communication\Controller\AbstractController;
use SprykerCommunity\Shared\SearchAnalyzerConfig\SearchIndexManagedScopeMatcher;

/**
 * @method \SprykerCommunity\Zed\SearchAnalyzerConfig\Communication\SearchAnalyzerConfigCommunicationFactory getFactory()
 * @method \SprykerCommunity\Zed\SearchAnalyzerConfig\Business\SearchAnalyzerConfigFacadeInterface getFacade()
 */
abstract class AbstractScopeController extends AbstractController
{
    /**
     * @var string
     */
    protected const URL_INDEX = '/search-analyzer-config/index';

    /**
     * Only ever a scope search-index-alias itself manages -- this package has nothing to render a config
     * into otherwise.
     *
     * @param string $sourceIdentifier
     * @param string $storeName
     */
    protected function findManagedScope(string $sourceIdentifier, string $storeName): ?SearchIndexScopeTransfer
    {
        return SearchIndexManagedScopeMatcher::match($this->getFacade()->getManagedScopes(), $sourceIdentifier, $storeName);
    }

    /**
     * Only ever accepts a same-site relative path -- this value round-trips through a hidden form field,
     * so treat it the same as any other client-controllable input rather than trusting it outright (an
     * open-redirect via `//evil.example` or `https://...` is the classic failure mode for this pattern).
     *
     * @param string $redirectTo
     */
    protected function resolveRedirectUrl(string $redirectTo): string
    {
        if ($redirectTo === '' || !str_starts_with($redirectTo, '/') || str_starts_with($redirectTo, '//')) {
            return static::URL_INDEX;
        }

        return $redirectTo;
    }

    /**
     * @param string $sourceIdentifier
     * @param string $storeName
     */
    protected function buildIndexUrl(string $sourceIdentifier, string $storeName): string
    {
        return sprintf('/search-analyzer-config/index?source=%s&store=%s', urlencode($sourceIdentifier), urlencode($storeName));
    }

    /**
     * @param string $sourceIdentifier
     * @param string $storeName
     */
    protected function buildEditUrl(string $sourceIdentifier, string $storeName): string
    {
        return sprintf('/search-analyzer-config/config/edit?source=%s&store=%s', urlencode($sourceIdentifier), urlencode($storeName));
    }

    /**
     * @param string $sourceIdentifier
     * @param string $storeName
     * @param string $listType One of SprykerCommunity\Shared\SearchAnalyzerConfig\SearchAnalyzerConfigConfig::LIST_TYPES.
     */
    protected function buildEditListUrl(string $sourceIdentifier, string $storeName, string $listType): string
    {
        return sprintf(
            '/search-analyzer-config/config/edit-list?source=%s&store=%s&list=%s',
            urlencode($sourceIdentifier),
            urlencode($storeName),
            urlencode($listType),
        );
    }

    /**
     * @param string $sourceIdentifier
     * @param string $storeName
     * @param string $filterType One of SprykerCommunity\Shared\SearchAnalyzerConfig\SearchAnalyzerConfigConfig::FILTER_TYPES.
     */
    protected function buildEditFilterUrl(string $sourceIdentifier, string $storeName, string $filterType): string
    {
        return sprintf(
            '/search-analyzer-config/config/edit-filter?source=%s&store=%s&filter=%s',
            urlencode($sourceIdentifier),
            urlencode($storeName),
            urlencode($filterType),
        );
    }
}
