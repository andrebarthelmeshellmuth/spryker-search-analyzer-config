<?php

/**
 * This file is part of the spryker-community/search-analyzer-config package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchAnalyzerConfig\Business\Exception;

use RuntimeException;

/**
 * Thrown by SearchAnalyzerConfigRenderer when a scope has an ACTIVE value for a field (e.g. a non-empty
 * synonym list) but the target analyzer's own `filter` chain does not already reference the named slot
 * that field needs (e.g. `sac_synonyms`). This package never adds a filter name to an analyzer's own
 * chain -- see the README, "How it works" -- so a missing slot is the project's own schema not having
 * declared and positioned that filter yet, not something this package can silently work around.
 *
 * @see \SprykerCommunity\Zed\SearchAnalyzerConfig\Business\Previewer\SearchAnalyzerConfigPreviewerInterface::validateAgainstLiveCluster()
 */
class SearchAnalyzerConfigMissingFilterSlotException extends RuntimeException
{
    /**
     * @param array<string> $missingSlotMessages
     */
    public function __construct(protected array $missingSlotMessages)
    {
        parent::__construct(implode(' ', $missingSlotMessages));
    }

    /**
     * @return array<string>
     */
    public function getMissingSlotMessages(): array
    {
        return $this->missingSlotMessages;
    }
}
