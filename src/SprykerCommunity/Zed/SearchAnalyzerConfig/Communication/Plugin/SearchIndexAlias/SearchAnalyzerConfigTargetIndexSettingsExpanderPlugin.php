<?php

/**
 * This file is part of the spryker-community/search-analyzer-config package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchAnalyzerConfig\Communication\Plugin\SearchIndexAlias;

use Generated\Shared\Transfer\SearchIndexScopeTransfer;
use Spryker\Zed\Kernel\Communication\AbstractPlugin;
use SprykerCommunity\Zed\SearchIndexAlias\Dependency\Plugin\TargetIndexSettingsExpanderPluginInterface;

/**
 * Registers this package into a search-index-alias rebuild's target-settings pipeline (see that
 * package's `TargetIndexSettingsExpanderPluginInterface` doc block for why this is the only integration
 * needed). A scope with no staged SearchAnalyzerConfig row returns $settings unchanged, per the
 * interface's own contract.
 *
 * @method \SprykerCommunity\Zed\SearchAnalyzerConfig\Business\SearchAnalyzerConfigFacadeInterface getFacade()
 * @method \SprykerCommunity\Zed\SearchAnalyzerConfig\Communication\SearchAnalyzerConfigCommunicationFactory getFactory()
 */
class SearchAnalyzerConfigTargetIndexSettingsExpanderPlugin extends AbstractPlugin implements TargetIndexSettingsExpanderPluginInterface
{
    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @param \Generated\Shared\Transfer\SearchIndexScopeTransfer $searchIndexScopeTransfer
     * @param array<string, mixed> $settings
     *
     * @return array<string, mixed>
     */
    public function expand(SearchIndexScopeTransfer $searchIndexScopeTransfer, array $settings): array
    {
        return $this->getFacade()->renderIntoSettings(
            $searchIndexScopeTransfer->getSourceIdentifierOrFail(),
            $searchIndexScopeTransfer->getStoreNameOrFail(),
            $settings,
        );
    }
}
