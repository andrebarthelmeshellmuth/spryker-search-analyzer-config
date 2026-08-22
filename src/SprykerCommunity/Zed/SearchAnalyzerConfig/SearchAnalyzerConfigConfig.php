<?php

/**
 * This file is part of the spryker-community/search-analyzer-config package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchAnalyzerConfig;

use Spryker\Zed\Kernel\AbstractBundleConfig;

class SearchAnalyzerConfigConfig extends AbstractBundleConfig
{
    /**
     * Stemmer `language` filter values a project may pick from in the GUI. Deliberately a fixed list
     * rather than accepting any Lucene-supported value freeform — every entry here is verified to exist
     * in this shop's actual OpenSearch/Elasticsearch version (see README, "Supported languages").
     *
     * @return array<string, string> Value => human-readable label.
     */
    public function getAllowedStemmerLanguages(): array
    {
        return [
            '' => 'None',
            'light_german' => 'German (light)',
            'minimal_english' => 'English (minimal)',
            'light_french' => 'French (light)',
        ];
    }

    /**
     * @return array<string, string> Value => human-readable label.
     */
    public function getAllowedNormalizationFilters(): array
    {
        return [
            '' => 'None',
            'german_normalization' => 'German',
        ];
    }

    /**
     * Built-in Lucene stopword set identifiers a project may pick from in the GUI when
     * `stopwordsMode = builtin`.
     *
     * @return array<string, string> Value => human-readable label.
     */
    public function getAllowedBuiltinStopwordsLanguages(): array
    {
        return [
            '_german_' => 'German',
            '_english_' => 'English',
            '_french_' => 'French',
        ];
    }
}
