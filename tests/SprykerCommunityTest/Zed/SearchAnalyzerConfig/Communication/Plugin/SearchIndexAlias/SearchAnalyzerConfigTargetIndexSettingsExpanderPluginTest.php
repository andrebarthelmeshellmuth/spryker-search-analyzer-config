<?php

/**
 * This file is part of the spryker-community/search-analyzer-config package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Zed\SearchAnalyzerConfig\Communication\Plugin\SearchIndexAlias;

use Codeception\Test\Unit;
use Generated\Shared\Transfer\SearchAnalyzerConfigTransfer;
use Generated\Shared\Transfer\SearchIndexScopeTransfer;
use Orm\Zed\SearchAnalyzerConfig\Persistence\SpySearchAnalyzerConfigQuery;
use SprykerCommunity\Shared\SearchAnalyzerConfig\SearchAnalyzerConfigConfig;
use SprykerCommunity\Zed\SearchAnalyzerConfig\Business\SearchAnalyzerConfigFacade;
use SprykerCommunity\Zed\SearchAnalyzerConfig\Communication\Plugin\SearchIndexAlias\SearchAnalyzerConfigTargetIndexSettingsExpanderPlugin;

/**
 * INTEGRATION TEST — goes through the real facade and a real database row, confirming this package's one
 * actual integration point with search-index-alias (see the plugin's own docblock) behaves the way a real
 * rebuild would exercise it: unchanged settings for an unstaged scope, spliced-in settings for a staged
 * one.
 *
 * Auto-generated group annotations
 *
 * @group SprykerCommunityTest
 * @group Zed
 * @group SearchAnalyzerConfig
 * @group Communication
 * @group Plugin
 * @group SearchIndexAlias
 * @group SearchAnalyzerConfigTargetIndexSettingsExpanderPluginTest
 * Add your own group annotations below this line
 * @group NeedsDatabase
 */
class SearchAnalyzerConfigTargetIndexSettingsExpanderPluginTest extends Unit
{
    /**
     * @var string
     */
    protected const TEST_SOURCE_IDENTIFIER = 'phpunit_source';

    /**
     * @var string
     */
    protected const TEST_STORE_NAME = 'PHPUNIT';

    protected function _before(): void
    {
        $this->cleanUp();
    }

    protected function _after(): void
    {
        $this->cleanUp();
    }

    protected function cleanUp(): void
    {
        SpySearchAnalyzerConfigQuery::create()
            ->filterBySourceIdentifier(static::TEST_SOURCE_IDENTIFIER)
            ->filterByStoreName(static::TEST_STORE_NAME)
            ->delete();
    }

    public function testExpandReturnsSettingsUnchangedForAnUnstagedScope(): void
    {
        $baseSettings = ['analysis' => ['analyzer' => ['fulltext_search_analyzer' => ['filter' => ['lowercase']]]]];

        $searchIndexScopeTransfer = (new SearchIndexScopeTransfer())
            ->setSourceIdentifier(static::TEST_SOURCE_IDENTIFIER)
            ->setStoreName(static::TEST_STORE_NAME);

        $result = (new SearchAnalyzerConfigTargetIndexSettingsExpanderPlugin())->expand($searchIndexScopeTransfer, $baseSettings);

        $this->assertSame($baseSettings, $result);
    }

    public function testExpandSplicesInFiltersForAStagedScope(): void
    {
        (new SearchAnalyzerConfigFacade())->save(
            (new SearchAnalyzerConfigTransfer())
                ->setSourceIdentifier(static::TEST_SOURCE_IDENTIFIER)
                ->setStoreName(static::TEST_STORE_NAME)
                ->setStemmerLanguage('light_german'),
            SearchAnalyzerConfigConfig::CHANGE_SOURCE_MANUAL,
            null,
        );

        // The project's own schema must already declare and chain the well-known 'sac_stemmer' slot --
        // this package only ever overwrites the DATA inside it, see SearchAnalyzerConfigRenderer's own
        // class doc block. Mirrors this demo shop's real de_page/at_page.json schema, which declares BOTH
        // analyzers this shop's own Pyz\Zed\SearchAnalyzerConfig\SearchAnalyzerConfigConfig targets below
        // — leaving one out here would make SearchAnalyzerConfigRenderer throw
        // SearchAnalyzerConfigMissingFilterSlotException (an active stemmerLanguage with no analyzer to
        // write it into at all).
        $baseSettings = [
            'analysis' => [
                'analyzer' => [
                    'fulltext_index_analyzer' => ['filter' => ['lowercase', 'sac_stemmer']],
                    'fulltext_search_analyzer' => ['filter' => ['lowercase', 'sac_stemmer']],
                ],
            ],
        ];

        $searchIndexScopeTransfer = (new SearchIndexScopeTransfer())
            ->setSourceIdentifier(static::TEST_SOURCE_IDENTIFIER)
            ->setStoreName(static::TEST_STORE_NAME);

        $result = (new SearchAnalyzerConfigTargetIndexSettingsExpanderPlugin())->expand($searchIndexScopeTransfer, $baseSettings);

        // Which analyzer(s) get spliced into is decided by SearchAnalyzerConfigConfig::getTargetAnalyzerNames()
        // — the out-of-the-box package default is empty (see that class's own docblock), but this demo
        // shop's own Pyz\Zed\SearchAnalyzerConfig\SearchAnalyzerConfigConfig override targets BOTH
        // 'fulltext_index_analyzer' and 'fulltext_search_analyzer', so a real rebuild through this shop's
        // Locator writes into both. The filter chain itself is never touched — only the data inside the
        // already-referenced slot.
        $this->assertSame(
            ['lowercase', 'sac_stemmer'],
            $result['analysis']['analyzer']['fulltext_index_analyzer']['filter'],
        );
        $this->assertSame(
            ['lowercase', 'sac_stemmer'],
            $result['analysis']['analyzer']['fulltext_search_analyzer']['filter'],
        );
        $this->assertSame(
            ['type' => 'stemmer', 'language' => 'light_german'],
            $result['analysis']['filter']['sac_stemmer'],
        );
    }
}
