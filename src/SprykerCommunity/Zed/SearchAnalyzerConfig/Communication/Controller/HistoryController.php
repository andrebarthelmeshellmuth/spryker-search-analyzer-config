<?php

/**
 * This file is part of the spryker-community/search-analyzer-config package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchAnalyzerConfig\Communication\Controller;

use SprykerCommunity\Zed\SearchAnalyzerConfig\Communication\Form\SearchAnalyzerConfigRestoreForm;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Throwable;

/**
 * A read-mostly audit log -- every action lives on the Overview/Edit pages instead, except Restore,
 * which only makes sense from within the history it restores from.
 */
class HistoryController extends AbstractScopeController
{
    /**
     * @param \Symfony\Component\HttpFoundation\Request $request
     *
     * @return array<string, mixed>
     */
    public function indexAction(Request $request): array
    {
        $sourceIdentifier = (string)$request->query->get('source', '');
        $storeName = (string)$request->query->get('store', '');

        if ($sourceIdentifier === '' || $storeName === '') {
            $this->addErrorMessage('Missing source/store.');

            return $this->viewResponse(['sourceIdentifier' => $sourceIdentifier, 'storeName' => $storeName, 'history' => []]);
        }

        $history = $this->getFacade()->getRevisionHistory($sourceIdentifier, $storeName);
        $redirectTo = sprintf('/search-analyzer-config/history/index?source=%s&store=%s', urlencode($sourceIdentifier), urlencode($storeName));

        $historyRows = [];

        foreach ($history as $searchAnalyzerConfigRevisionTransfer) {
            $historyRows[] = [
                'revision' => $searchAnalyzerConfigRevisionTransfer,
                'restoreFormView' => $this->getFactory()
                    ->createSearchAnalyzerConfigRestoreForm($sourceIdentifier, $storeName, $searchAnalyzerConfigRevisionTransfer->getRevisionOrFail())
                    ->createView(),
            ];
        }

        return $this->viewResponse([
            'sourceIdentifier' => $sourceIdentifier,
            'storeName' => $storeName,
            'historyRows' => $historyRows,
            'redirectTo' => $redirectTo,
        ]);
    }

    /**
     * @param \Symfony\Component\HttpFoundation\Request $request
     */
    public function restoreAction(Request $request): RedirectResponse
    {
        $restoreForm = $this->getFactory()->createSearchAnalyzerConfigRestoreForm('', '', 0)->handleRequest($request);

        if (!$restoreForm->isSubmitted() || !$restoreForm->isValid()) {
            $this->addErrorMessage('CSRF token is not valid.');

            return $this->redirectResponse(static::URL_INDEX);
        }

        /** @var array<string, mixed> $formData */
        $formData = $restoreForm->getData();
        /** @var string $sourceIdentifier */
        $sourceIdentifier = $formData[SearchAnalyzerConfigRestoreForm::FIELD_SOURCE_IDENTIFIER];
        /** @var string $storeName */
        $storeName = $formData[SearchAnalyzerConfigRestoreForm::FIELD_STORE_NAME];
        $revision = (int)$formData[SearchAnalyzerConfigRestoreForm::FIELD_REVISION];
        $historyUrl = sprintf('/search-analyzer-config/history/index?source=%s&store=%s', urlencode($sourceIdentifier), urlencode($storeName));
        /** @var string $requestedRedirectTo */
        $requestedRedirectTo = $formData[SearchAnalyzerConfigRestoreForm::FIELD_REDIRECT_TO] ?? '';
        // Empty (History's own Restore buttons never set it) means "redirect back to History" -- otherwise
        // the caller (e.g. the Edit page's "Reset changes" button) picked its own safe redirect target.
        $redirectUrl = $requestedRedirectTo !== '' ? $this->resolveRedirectUrl($requestedRedirectTo) : $historyUrl;

        if ($this->findManagedScope($sourceIdentifier, $storeName) === null) {
            $this->addErrorMessage(sprintf('"%s" / "%s" is not a search-index-alias managed scope.', $sourceIdentifier, $storeName));

            return $this->redirectResponse($redirectUrl);
        }

        try {
            $errors = $this->getFacade()->restoreRevision($sourceIdentifier, $storeName, $revision, 'zed-gui');
        } catch (Throwable $throwable) {
            $this->addErrorMessage(sprintf('Restore failed: %s', $throwable->getMessage()));

            return $this->redirectResponse($redirectUrl);
        }

        if ($errors !== []) {
            foreach ($errors as $error) {
                $this->addErrorMessage($error);
            }

            return $this->redirectResponse($redirectUrl);
        }

        $this->addSuccessMessage(sprintf('Revision %d restored as a new revision. It is NOT live yet -- use "Apply" on the Overview page to trigger a rebuild.', $revision));

        return $this->redirectResponse($redirectUrl);
    }
}
