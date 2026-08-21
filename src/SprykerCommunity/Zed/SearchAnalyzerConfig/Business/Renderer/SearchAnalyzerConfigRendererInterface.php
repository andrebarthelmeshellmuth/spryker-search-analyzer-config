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
     * package to drive. A target analyzer that doesn't reference a given slot is a deliberate per-analyzer
     * opt-out, not an error -- that field is simply skipped for that analyzer (see collectMissingSlotWarnings()
     * for the non-fatal, pre-save version of this same detection).
     *
     * @param \Generated\Shared\Transfer\SearchAnalyzerConfigTransfer $searchAnalyzerConfigTransfer
     * @param array<string, mixed> $baseSettings
     * @param array<string> $targetAnalyzerNames
     *
     * @throws \SprykerCommunity\Zed\SearchAnalyzerConfig\Business\Exception\SearchAnalyzerConfigMissingFilterSlotException
     *  Only when a target analyzer name isn't declared in $baseSettings at all.
     *
     * @return array<string, mixed>
     */
    public function render(
        SearchAnalyzerConfigTransfer $searchAnalyzerConfigTransfer,
        array $baseSettings,
        array $targetAnalyzerNames,
    ): array;

    /**
     * Same per-analyzer slot detection as render(), but never mutates $baseSettings and never throws for a
     * slot that simply isn't referenced by one of the target analyzers -- returns a warning message for
     * each such active-field/analyzer combination instead, for a caller that wants to flag it and let the
     * user confirm before saving.
     *
     * @param \Generated\Shared\Transfer\SearchAnalyzerConfigTransfer $searchAnalyzerConfigTransfer
     * @param array<string, mixed> $baseSettings
     * @param array<string> $targetAnalyzerNames
     *
     * @return array<string>
     */
    public function collectMissingSlotWarnings(
        SearchAnalyzerConfigTransfer $searchAnalyzerConfigTransfer,
        array $baseSettings,
        array $targetAnalyzerNames,
    ): array;
}
