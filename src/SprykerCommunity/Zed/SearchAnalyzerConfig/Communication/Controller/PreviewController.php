<?php

/**
 * This file is part of the spryker-community/search-analyzer-config package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchAnalyzerConfig\Communication\Controller;

use Generated\Shared\Transfer\SearchAnalyzerConfigPreviewResultTransfer;
use SprykerCommunity\Zed\SearchAnalyzerConfig\Business\Exception\SearchAnalyzerConfigMissingFilterSlotException;
use SprykerCommunity\Zed\SearchAnalyzerConfig\Business\Exception\SearchAnalyzerConfigScopeNotManagedException;
use SprykerCommunity\Zed\SearchAnalyzerConfig\Communication\Form\SearchAnalyzerConfigPreviewForm;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Throwable;

class PreviewController extends AbstractScopeController
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

        if ($this->findManagedScope($sourceIdentifier, $storeName) === null) {
            $this->addErrorMessage(sprintf('"%s" / "%s" is not a search-index-alias managed scope.', $sourceIdentifier, $storeName));

            return $this->viewResponse(['previewFormView' => null, 'result' => null]);
        }

        if ($this->getFactory()->getConfig()->getTargetAnalyzerNames() === []) {
            $this->addErrorMessage('No target analyzer is configured (SearchAnalyzerConfigConfig::getTargetAnalyzerNames() is empty) -- there is nothing to preview against. See README, "Configuration".');

            return $this->viewResponse(['previewFormView' => null, 'result' => null]);
        }

        $previewForm = $this->getFactory()->createSearchAnalyzerConfigPreviewForm($sourceIdentifier, $storeName)->handleRequest($request);
        $result = null;

        if ($previewForm->isSubmitted() && $previewForm->isValid()) {
            $result = $this->runPreview($previewForm, $sourceIdentifier, $storeName);
        }

        return $this->viewResponse([
            'sourceIdentifier' => $sourceIdentifier,
            'storeName' => $storeName,
            'previewFormView' => $previewForm->createView(),
            'result' => $result,
        ]);
    }

    /**
     * @param \Symfony\Component\Form\FormInterface $previewForm
     * @param string $sourceIdentifier
     * @param string $storeName
     */
    protected function runPreview(
        FormInterface $previewForm,
        string $sourceIdentifier,
        string $storeName,
    ): ?SearchAnalyzerConfigPreviewResultTransfer {
        /** @var array<string, mixed> $formData */
        $formData = $previewForm->getData();
        /** @var string $targetAnalyzerName */
        $targetAnalyzerName = $formData[SearchAnalyzerConfigPreviewForm::FIELD_TARGET_ANALYZER_NAME];
        /** @var string $inputText */
        $inputText = $formData[SearchAnalyzerConfigPreviewForm::FIELD_INPUT_TEXT];

        try {
            return $this->getFacade()->preview($sourceIdentifier, $storeName, $targetAnalyzerName, $inputText);
        } catch (SearchAnalyzerConfigScopeNotManagedException $searchAnalyzerConfigScopeNotManagedException) {
            $this->addErrorMessage($searchAnalyzerConfigScopeNotManagedException->getMessage());

            return null;
        } catch (SearchAnalyzerConfigMissingFilterSlotException $searchAnalyzerConfigMissingFilterSlotException) {
            foreach ($searchAnalyzerConfigMissingFilterSlotException->getMissingSlotMessages() as $missingSlotMessage) {
                $this->addErrorMessage($missingSlotMessage);
            }

            return null;
        } catch (Throwable $throwable) {
            $this->addErrorMessage(sprintf('Preview failed: %s', $throwable->getMessage()));

            return null;
        }
    }
}
