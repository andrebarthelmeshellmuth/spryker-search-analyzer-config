<?php

/**
 * This file is part of the spryker-community/search-analyzer-config package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchAnalyzerConfig\Communication\Console;

use Spryker\Zed\Kernel\Communication\Console\Console;
use SprykerCommunity\Shared\SearchAnalyzerConfig\SearchIndexManagedScopeMatcher;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * CLI counterpart of the Copy page: a hard override of the target scope, no merge semantics -- see
 * `SearchAnalyzerConfigCopier`'s own doc block. Does not apply anything; run
 * `search-analyzer-config:apply` afterward if the copy should reach a live index.
 *
 * @method \SprykerCommunity\Zed\SearchAnalyzerConfig\Business\SearchAnalyzerConfigFacadeInterface getFacade()
 */
class SearchAnalyzerConfigCopyConsole extends Console
{
    /**
     * @var string
     */
    public const COMMAND_NAME = 'search-analyzer-config:copy';

    /**
     * @var string
     */
    public const COMMAND_DESCRIPTION = 'Copies a scope\'s staged config onto another scope, overwriting whatever the target had.';

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
    public const ARGUMENT_TARGET_SOURCE = 'target-source';

    /**
     * @var string
     */
    public const ARGUMENT_TARGET_STORE = 'target-store';

    protected function configure(): void
    {
        $this->setName(static::COMMAND_NAME);
        $this->setDescription(static::COMMAND_DESCRIPTION);
        $this->addArgument(static::ARGUMENT_SOURCE, InputArgument::REQUIRED, 'Source scope sourceIdentifier.');
        $this->addArgument(static::ARGUMENT_STORE, InputArgument::REQUIRED, 'Source scope store name.');
        $this->addArgument(static::ARGUMENT_TARGET_SOURCE, InputArgument::REQUIRED, 'Target scope sourceIdentifier.');
        $this->addArgument(static::ARGUMENT_TARGET_STORE, InputArgument::REQUIRED, 'Target scope store name.');

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
        /** @var string $targetSourceIdentifier */
        $targetSourceIdentifier = $input->getArgument(static::ARGUMENT_TARGET_SOURCE);
        /** @var string $targetStoreName */
        $targetStoreName = $input->getArgument(static::ARGUMENT_TARGET_STORE);

        $managedScopes = $this->getFacade()->getManagedScopes();

        if (SearchIndexManagedScopeMatcher::match($managedScopes, $sourceIdentifier, $storeName) === null) {
            $this->error(sprintf('"%s" / "%s" is not a search-index-alias managed scope.', $sourceIdentifier, $storeName));

            return static::CODE_ERROR;
        }

        if (SearchIndexManagedScopeMatcher::match($managedScopes, $targetSourceIdentifier, $targetStoreName) === null) {
            $this->error(sprintf('"%s" / "%s" is not a search-index-alias managed scope.', $targetSourceIdentifier, $targetStoreName));

            return static::CODE_ERROR;
        }

        $errors = $this->getFacade()->copy($sourceIdentifier, $targetSourceIdentifier, $storeName, $targetStoreName, 'console');

        if ($errors !== []) {
            $this->error(implode(PHP_EOL, $errors));

            return static::CODE_ERROR;
        }

        $copySearchAnalyzerConfigTransfer = $this->getFacade()->findByScope($targetSourceIdentifier, $targetStoreName);

        $this->success(sprintf(
            'Copied "%s"/"%s" onto "%s"/"%s" (new revision %d).',
            $sourceIdentifier,
            $storeName,
            $targetSourceIdentifier,
            $targetStoreName,
            $copySearchAnalyzerConfigTransfer?->getRevision() ?? 0,
        ));

        return static::CODE_SUCCESS;
    }
}
