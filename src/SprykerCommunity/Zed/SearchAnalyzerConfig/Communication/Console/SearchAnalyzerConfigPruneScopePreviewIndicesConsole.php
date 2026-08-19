<?php

/**
 * This file is part of the spryker-community/search-analyzer-config package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchAnalyzerConfig\Communication\Console;

use Spryker\Zed\Kernel\Communication\Console\Console;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Safety-net cleanup for `SearchAnalyzerConfigPreviewer`'s throwaway preview indices: each one is deleted
 * in its own request's `finally` block, so this should normally find nothing -- it only matters after a
 * preview request crashed or timed out before that cleanup ran. Safe to run on a schedule (e.g. daily
 * cron), same as `search-index-alias:prune` is for its own rebuild targets.
 *
 * @method \SprykerCommunity\Zed\SearchAnalyzerConfig\Business\SearchAnalyzerConfigFacadeInterface getFacade()
 */
class SearchAnalyzerConfigPruneScopePreviewIndicesConsole extends Console
{
    /**
     * @var string
     */
    public const COMMAND_NAME = 'search-analyzer-config:prune-preview-indices';

    /**
     * @var string
     */
    public const COMMAND_DESCRIPTION = 'Deletes any orphaned preview-page throwaway index older than an hour -- a safety net for a preview request that crashed before its own cleanup ran.';

    protected function configure(): void
    {
        $this->setName(static::COMMAND_NAME);
        $this->setDescription(static::COMMAND_DESCRIPTION);

        parent::configure();
    }

    /**
     * @phpcsSuppress SlevomatCodingStandard.Functions.UnusedParameter $input is mandated by the Console base class; this command takes no arguments/options.
     *
     * @param \Symfony\Component\Console\Input\InputInterface $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $deletedIndexNames = $this->getFacade()->pruneOrphanedPreviewIndices();

        if ($deletedIndexNames === []) {
            $this->info('No orphaned preview index found.');

            return static::CODE_SUCCESS;
        }

        foreach ($deletedIndexNames as $deletedIndexName) {
            $output->writeln(sprintf('Deleted orphaned preview index "%s".', $deletedIndexName));
        }

        return static::CODE_SUCCESS;
    }
}
