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
     * Which named analyzer(s) in a rebuild target's live `settings.index.analysis.analyzer.*` this
     * package should look for its own well-known filter slots on (see SearchAnalyzerConfigRenderer's own
     * class doc block -- this package never adds a filter to an analyzer's chain, only overwrites the
     * data inside a slot the project's own schema already declared and positioned there). Empty by
     * default -- a fresh install renders no-op settings until a project points this at its own real
     * search-time analyzer name(s), e.g. `['fulltext_search_analyzer']` for this shop's own
     * `src/Pyz/Shared/Search/Schema/*.json`. Override in a project's own
     * `Pyz\Zed\SearchAnalyzerConfig\SearchAnalyzerConfigConfig`.
     *
     * @api
     *
     * @return array<string>
     */
    public function getTargetAnalyzerNames(): array
    {
        return [];
    }

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
