<?php

/**
 * This file is part of the spryker-community/search-analyzer-config package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchAnalyzerConfig\Communication\Controller;

use Generated\Shared\Transfer\SearchIndexScopeTransfer;
use SprykerCommunity\Shared\SearchAnalyzerConfig\SearchIndexManagedScopeMatcher;
use SprykerCommunity\Zed\SearchIndexAlias\Persistence\Exception\ConcurrentRolloutException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

class IndexController extends AbstractScopeController
{
    /**
     * @var string
     */
    protected const DEFAULT_SOURCE = 'page';

    /**
     * @param \Symfony\Component\HttpFoundation\Request $request
     *
     * @return array<string, mixed>
     */
    public function indexAction(Request $request): array
    {
        $searchAnalyzerConfigFacade = $this->getFacade();
        $selectedSource = (string)$request->query->get('source', static::DEFAULT_SOURCE);
        $selectedStore = (string)$request->query->get('store', '');

        $managedScopes = $searchAnalyzerConfigFacade->getManagedScopes();
        [$sources, $stores] = $this->collectSourcesAndStores($managedScopes);
        $searchIndexScopeTransfer = $this->resolveScope($managedScopes, $selectedSource, $selectedStore);

        $viewData = [
            'sources' => $sources,
            'selectedSource' => $selectedSource,
            'stores' => $stores,
            'selectedStore' => $selectedStore,
            'scope' => $searchIndexScopeTransfer,
        ];

        if ($searchIndexScopeTransfer === null) {
            return $this->viewResponse($viewData);
        }

        $viewData += $this->buildScopeViewData($selectedSource, $selectedStore);

        return $this->viewResponse($viewData);
    }

    /**
     * @param string $sourceIdentifier
     * @param string $storeName
     *
     * @return array<string, mixed>
     */
    protected function buildScopeViewData(string $sourceIdentifier, string $storeName): array
    {
        $searchAnalyzerConfigFacade = $this->getFacade();
        $searchAnalyzerConfigTransfer = $searchAnalyzerConfigFacade->findByScope($sourceIdentifier, $storeName);
        $redirectTo = $this->buildIndexUrl($sourceIdentifier, $storeName);

        return [
            'config' => $searchAnalyzerConfigTransfer,
            'applyFormView' => $this->getFactory()->createSearchAnalyzerConfigScopeForm($sourceIdentifier, $storeName, $redirectTo)->createView(),
        ];
    }

    /**
     * @param \Symfony\Component\HttpFoundation\Request $request
     */
    public function applyAction(Request $request): RedirectResponse
    {
        $searchAnalyzerConfigScopeForm = $this->getFactory()->createSearchAnalyzerConfigScopeForm('', '')->handleRequest($request);

        if (!$searchAnalyzerConfigScopeForm->isSubmitted() || !$searchAnalyzerConfigScopeForm->isValid()) {
            $this->addErrorMessage('CSRF token is not valid.');

            return $this->redirectResponse(static::URL_INDEX);
        }

        $formData = $searchAnalyzerConfigScopeForm->getData();
        /** @var string $sourceIdentifier */
        $sourceIdentifier = $formData['sourceIdentifier'];
        /** @var string $storeName */
        $storeName = $formData['storeName'];
        $redirectUrl = $this->resolveRedirectUrl((string)$formData['redirectTo']);

        try {
            $searchIndexRolloutTransfer = $this->getFacade()->requestRebuild($sourceIdentifier, $storeName, 'zed-gui');
        } catch (ConcurrentRolloutException $concurrentRolloutException) {
            $this->addErrorMessage($concurrentRolloutException->getMessage());

            return $this->redirectResponse($redirectUrl);
        }

        if ($searchIndexRolloutTransfer === null) {
            $this->addErrorMessage(sprintf('"%s" / "%s" is not a search-index-alias managed scope.', $sourceIdentifier, $storeName));

            return $this->redirectResponse($redirectUrl);
        }

        $this->addSuccessMessage(sprintf(
            'Rebuild %d requested (target=%s). The rebuild worker will pick it up shortly -- refresh to check progress.',
            $searchIndexRolloutTransfer->getIdSearchIndexRollout() ?? 0,
            $searchIndexRolloutTransfer->getTargetIndexName() ?? '-',
        ));

        return $this->redirectResponse($redirectUrl);
    }

    /**
     * @param array<\Generated\Shared\Transfer\SearchIndexScopeTransfer> $managedScopes
     *
     * @return array{0: array<int, string>, 1: array<int, string>}
     */
    protected function collectSourcesAndStores(array $managedScopes): array
    {
        $sources = [];
        $stores = [];

        foreach ($managedScopes as $searchIndexScopeTransfer) {
            $sources[$searchIndexScopeTransfer->getSourceIdentifierOrFail()] = true;
            $stores[$searchIndexScopeTransfer->getStoreNameOrFail()] = true;
        }

        $sources = array_keys($sources);
        sort($sources);
        $stores = array_keys($stores);
        sort($stores);

        return [$sources, $stores];
    }

    /**
     * @param array<\Generated\Shared\Transfer\SearchIndexScopeTransfer> $managedScopes
     * @param string $selectedSource
     * @param string $selectedStore
     */
    protected function resolveScope(array $managedScopes, string $selectedSource, string $selectedStore): ?SearchIndexScopeTransfer
    {
        if ($selectedSource === '' || $selectedStore === '') {
            return null;
        }

        return SearchIndexManagedScopeMatcher::match($managedScopes, $selectedSource, $selectedStore);
    }
}
