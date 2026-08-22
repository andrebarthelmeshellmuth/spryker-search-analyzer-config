<?php

/**
 * This file is part of the spryker-community/search-analyzer-config package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchAnalyzerConfig\Communication\Console;

use Spryker\Zed\Kernel\Communication\Console\Console;
use SprykerCommunity\Zed\SearchIndexAlias\Persistence\Exception\ConcurrentRolloutException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * CLI counterpart of the Overview page's "Apply" button -- the explicit, separate action the plan's
 * locked-in two-step Apply UX requires (Save only stages a config; nothing ever rebuilds automatically as
 * a side effect of a save). Requests an async rebuild via search-index-alias; the rebuild worker
 * (`search-index-alias:rebuild-worker`) does the actual work.
 *
 * @method \SprykerCommunity\Zed\SearchAnalyzerConfig\Business\SearchAnalyzerConfigFacadeInterface getFacade()
 */
class SearchAnalyzerConfigApplyConsole extends Console
{
    /**
     * @var string
     */
    public const COMMAND_NAME = 'search-analyzer-config:apply';

    /**
     * @var string
     */
    public const COMMAND_DESCRIPTION = 'Requests a search-index-alias rebuild for a scope, so its currently-staged analyzer config is materialized into a fresh target index. Async -- the rebuild worker must be running.';

    /**
     * @var string
     */
    public const ARGUMENT_SOURCE = 'source';

    /**
     * @var string
     */
    public const ARGUMENT_STORE = 'store';

    protected function configure(): void
    {
        $this->setName(static::COMMAND_NAME);
        $this->setDescription(static::COMMAND_DESCRIPTION);
        $this->addArgument(static::ARGUMENT_SOURCE, InputArgument::REQUIRED, 'sourceIdentifier, e.g. "page".');
        $this->addArgument(static::ARGUMENT_STORE, InputArgument::REQUIRED, 'Store name, e.g. "DE".');

        parent::configure();
    }

    /**
     * @phpcsSuppress SlevomatCodingStandard.Functions.UnusedParameter $output is mandated by the Console base class; this command only ever uses $this->error()/$this->success().
     *
     * @param \Symfony\Component\Console\Input\InputInterface $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var string $sourceIdentifier */
        $sourceIdentifier = $input->getArgument(static::ARGUMENT_SOURCE);
        /** @var string $storeName */
        $storeName = $input->getArgument(static::ARGUMENT_STORE);

        try {
            $searchIndexRolloutTransfer = $this->getFacade()->requestRebuild($sourceIdentifier, $storeName, 'console');
        } catch (ConcurrentRolloutException $concurrentRolloutException) {
            $this->error($concurrentRolloutException->getMessage());

            return static::CODE_ERROR;
        }

        if ($searchIndexRolloutTransfer === null) {
            $this->error(sprintf('"%s" / "%s" is not a search-index-alias managed scope.', $sourceIdentifier, $storeName));

            return static::CODE_ERROR;
        }

        $this->success(sprintf(
            'Rebuild %d requested for "%s" / "%s" (target=%s). The rebuild worker will pick it up shortly.',
            $searchIndexRolloutTransfer->getIdSearchIndexRollout() ?? 0,
            $sourceIdentifier,
            $storeName,
            $searchIndexRolloutTransfer->getTargetIndexName() ?? '-',
        ));

        return static::CODE_SUCCESS;
    }
}
