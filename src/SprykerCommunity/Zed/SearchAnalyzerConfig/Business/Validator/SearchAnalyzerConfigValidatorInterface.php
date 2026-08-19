<?php

/**
 * This file is part of the spryker-community/search-analyzer-config package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchAnalyzerConfig\Business\Validator;

use Generated\Shared\Transfer\SearchAnalyzerConfigTransfer;

interface SearchAnalyzerConfigValidatorInterface
{
    /**
     * @param \Generated\Shared\Transfer\SearchAnalyzerConfigTransfer $searchAnalyzerConfigTransfer
     *
     * @return array<string> Human-readable validation error messages, empty when valid.
     */
    public function validate(SearchAnalyzerConfigTransfer $searchAnalyzerConfigTransfer): array;
}
