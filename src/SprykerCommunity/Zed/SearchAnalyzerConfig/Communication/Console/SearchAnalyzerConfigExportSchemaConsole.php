<?php

/**
 * This file is part of the spryker-community/search-analyzer-config package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchAnalyzerConfig\Communication\Console;

use Spryker\Zed\Kernel\Communication\Console\Console;
use SprykerCommunity\Zed\SearchAnalyzerConfig\Business\Exception\SearchAnalyzerConfigInvalidTermException;
use SprykerCommunity\Zed\SearchAnalyzerConfig\Business\Renderer\SearchAnalyzerConfigRenderer;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * For a GREENFIELD install only: a scope's ordinary path is DB -> search-index-alias rebuild, which
 * clones the LIVE index's settings and this package writes into slots the project's own schema already
 * declared and chained -- but a brand-new scope has no live index yet for a rebuild to clone from (see the
 * README, "The schema-JSON path is unusable for ongoing updates" -- a rebuild never re-reads a project's
 * schema JSON, only the live index's settings). For that one case, the schema JSON a project's own
 * `search:setup` reads to CREATE the very first index is the only legitimate place this config can reach.
 * This command renders the currently-staged config into a standalone `analysis` JSON fragment for an
 * operator to merge into that project's own schema file by hand -- it never edits a project's files
 * itself, since assuming a specific schema file's shape or path would break portability to every other
 * adopter's own project layout. It pre-populates a starting chain from
 * `SearchAnalyzerConfigRenderer::CHAIN_VISIBLE_SLOT_NAMES_IN_RECOMMENDED_ORDER` -- see that constant's own
 * doc block -- since this package never adds a slot name to a chain itself; renderer output for an
 * inactive field is a definitive empty body (see the renderer's own class doc block), not an absent one.
 *
 * @method \SprykerCommunity\Zed\SearchAnalyzerConfig\Business\SearchAnalyzerConfigFacadeInterface getFacade()
 */
class SearchAnalyzerConfigExportSchemaConsole extends Console
{
    /**
     * @var string
     */
    public const COMMAND_NAME = 'search-analyzer-config:export-schema';

    /**
     * @var string
     */
    public const COMMAND_DESCRIPTION = 'Renders a scope\'s staged config as a standalone `analysis` JSON fragment, for a GREENFIELD install to merge by hand into its own schema JSON before the first `search:setup` (see the command\'s own doc block for why this is a one-time, manual-merge exception to the normal DB-to-rebuild flow).';

    /**
     * @var string
     */
    public const ARGUMENT_SOURCE = 'source';

    /**
     * @var string
     */
    public const ARGUMENT_STORE = 'store';

    /**
     * @var string
     */
    public const ARGUMENT_TARGET_ANALYZER = 'target-analyzer';

    protected function configure(): void
    {
        $this->setName(static::COMMAND_NAME);
        $this->setDescription(static::COMMAND_DESCRIPTION);
        $this->addArgument(static::ARGUMENT_SOURCE, InputArgument::REQUIRED, 'sourceIdentifier, e.g. "page".');
        $this->addArgument(static::ARGUMENT_STORE, InputArgument::REQUIRED, 'Store name, e.g. "DE".');
        $this->addArgument(static::ARGUMENT_TARGET_ANALYZER, InputArgument::REQUIRED, 'The analyzer name in your project\'s own schema JSON to render this config for, e.g. "fulltext_search_analyzer".');

        parent::configure();
    }

    /**
     * @param \Symfony\Component\Console\Input\InputInterface $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var string $sourceIdentifier */
        $sourceIdentifier = $input->getArgument(static::ARGUMENT_SOURCE);
        /** @var string $storeName */
        $storeName = $input->getArgument(static::ARGUMENT_STORE);
        /** @var string $targetAnalyzerName */
        $targetAnalyzerName = $input->getArgument(static::ARGUMENT_TARGET_ANALYZER);

        if ($this->getFacade()->findByScope($sourceIdentifier, $storeName) === null) {
            $this->error(sprintf('No config staged for source "%s" / store "%s" -- nothing to export.', $sourceIdentifier, $storeName));

            return static::CODE_ERROR;
        }

        // Greenfield means there is no live settings fragment to clone, and this package never adds a slot
        // name to an analyzer's own chain (see SearchAnalyzerConfigRenderer's class doc block) -- so the
        // starting chain here is this package's own recommended slot order, for the operator to position
        // within their own chain (e.g. ahead of an n-gram/edge-ngram/shingle filter, see the README's
        // "Limitations") rather than something this command could ever determine on its own.
        $baseSettings = [
            'analysis' => [
                'analyzer' => [
                    $targetAnalyzerName => ['filter' => SearchAnalyzerConfigRenderer::CHAIN_VISIBLE_SLOT_NAMES_IN_RECOMMENDED_ORDER],
                ],
            ],
        ];
        try {
            $renderedSettings = $this->getFacade()->renderIntoSettings($sourceIdentifier, $storeName, $baseSettings);
        } catch (SearchAnalyzerConfigInvalidTermException $searchAnalyzerConfigInvalidTermException) {
            $this->error($searchAnalyzerConfigInvalidTermException->getMessage());

            return static::CODE_ERROR;
        }

        $output->writeln(sprintf(
            '// Merge into your schema JSON\'s settings.index.analysis: add the "filter" entries below into your "%s" analyzer\'s own filter list (the order shown is only a recommended starting point -- position each slot to satisfy your own project\'s constraints, e.g. ahead of any n-gram/edge-ngram/shingle filter), and add every named filter under "filter" verbatim.',
            $targetAnalyzerName,
        ));
        $output->writeln((string)json_encode($renderedSettings['analysis'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return static::CODE_SUCCESS;
    }
}
