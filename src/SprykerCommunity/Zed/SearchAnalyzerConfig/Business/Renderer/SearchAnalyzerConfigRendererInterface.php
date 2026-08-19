<?php

/**
 * This file is part of the spryker-community/search-analyzer-config package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchAnalyzerConfig\Business\Renderer;

use Generated\Shared\Transfer\SearchAnalyzerConfigTransfer;

interface SearchAnalyzerConfigRendererInterface
{
    /**
     * Overwrites the DATA inside this package's own well-known filter slots (`sac_normalization`,
     * `sac_decompound`, `sac_synonyms`, `sac_stopwords`, `sac_keyword_marker`, `sac_stemmer`) on a live
     * index's `settings.index` fragment (the shape `IndexCloner::getFilteredSettings()` returns) --
     * NEVER adds a filter name to an analyzer's own `filter` chain, and never reorders or removes
     * anything already there. A slot only gets written when the target analyzer's own chain already
     * references it; the project's own schema owns declaring and positioning every slot it wants this
     * package to drive.
     *
     * @param \Generated\Shared\Transfer\SearchAnalyzerConfigTransfer $searchAnalyzerConfigTransfer
     * @param array<string, mixed> $baseSettings
     * @param array<string> $targetAnalyzerNames
     *
     * @throws \SprykerCommunity\Zed\SearchAnalyzerConfig\Business\Exception\SearchAnalyzerConfigMissingFilterSlotException
     *  When a field has an active value but its slot isn't referenced by a target analyzer's own chain.
     *
     * @return array<string, mixed>
     */
    public function render(
        SearchAnalyzerConfigTransfer $searchAnalyzerConfigTransfer,
        array $baseSettings,
        array $targetAnalyzerNames,
    ): array;
}
