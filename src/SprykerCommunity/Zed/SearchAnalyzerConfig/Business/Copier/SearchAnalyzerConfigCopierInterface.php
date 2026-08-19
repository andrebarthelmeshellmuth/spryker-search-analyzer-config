<?php

/**
 * This file is part of the spryker-community/search-analyzer-config package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchAnalyzerConfig\Business\Copier;

use Generated\Shared\Transfer\SearchAnalyzerConfigTransfer;

interface SearchAnalyzerConfigCopierInterface
{
    /**
     * A hard override, not a merge: every editable field and every term list on the returned transfer
     * comes from $sourceSearchAnalyzerConfigTransfer, verbatim. Whatever the target scope currently has
     * staged is meant to be entirely replaced by the caller (persistence layer), not combined with it --
     * deliberately unlike search-ranking's Scope Copy, per the plan's own decision.
     *
     * @param \Generated\Shared\Transfer\SearchAnalyzerConfigTransfer $sourceSearchAnalyzerConfigTransfer
     * @param string $targetSourceIdentifier
     * @param string $targetStoreName
     */
    public function copy(
        SearchAnalyzerConfigTransfer $sourceSearchAnalyzerConfigTransfer,
        string $targetSourceIdentifier,
        string $targetStoreName,
    ): SearchAnalyzerConfigTransfer;
}
