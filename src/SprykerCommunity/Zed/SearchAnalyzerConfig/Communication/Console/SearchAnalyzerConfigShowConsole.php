<?php

/**
 * This file is part of the spryker-community/search-analyzer-config package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchAnalyzerConfig\Communication\Console;

use Spryker\Zed\Kernel\Communication\Console\Console;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @method \SprykerCommunity\Zed\SearchAnalyzerConfig\Business\SearchAnalyzerConfigFacadeInterface getFacade()
 */
class SearchAnalyzerConfigShowConsole extends Console
{
    /**
     * @var string
     */
    public const COMMAND_NAME = 'search-analyzer-config:show';

    /**
     * @var string
     */
    public const COMMAND_DESCRIPTION = 'Dumps a scope\'s staged config: the five editable fields, all four term lists, and revision/apply bookkeeping.';

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
     * @param \Symfony\Component\Console\Input\InputInterface $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var string $sourceIdentifier */
        $sourceIdentifier = $input->getArgument(static::ARGUMENT_SOURCE);
        /** @var string $storeName */
        $storeName = $input->getArgument(static::ARGUMENT_STORE);

        $searchAnalyzerConfigTransfer = $this->getFacade()->findByScope($sourceIdentifier, $storeName);

        if ($searchAnalyzerConfigTransfer === null) {
            $this->info(sprintf('No config staged for source "%s" / store "%s".', $sourceIdentifier, $storeName));

            return static::CODE_SUCCESS;
        }

        $output->writeln(sprintf('<info>%s / %s</info> (revision %d, applied revision %s)', $sourceIdentifier, $storeName, $searchAnalyzerConfigTransfer->getRevision() ?? 0, $searchAnalyzerConfigTransfer->getAppliedRevision() ?? '-'));
        $output->writeln(sprintf('  stemmerLanguage: %s', $searchAnalyzerConfigTransfer->getStemmerLanguage() ?? '-'));
        $output->writeln(sprintf('  normalizationFilter: %s', $searchAnalyzerConfigTransfer->getNormalizationFilter() ?? '-'));
        $output->writeln(sprintf('  stopwordsMode: %s (builtin language: %s)', $searchAnalyzerConfigTransfer->getStopwordsMode() ?? '-', $searchAnalyzerConfigTransfer->getStopwordsBuiltinLanguage() ?? '-'));
        $output->writeln(sprintf('  decompoundEnabled: %s', $searchAnalyzerConfigTransfer->getDecompoundEnabled() ? 'yes' : 'no'));

        $this->printTermList($output, 'decompoundWords', $searchAnalyzerConfigTransfer->getDecompoundWords());
        $this->printTermList($output, 'synonyms', $searchAnalyzerConfigTransfer->getSynonyms());
        $this->printTermList($output, 'stopwords', $searchAnalyzerConfigTransfer->getStopwords());
        $this->printTermList($output, 'doNotDecompoundTerms', $searchAnalyzerConfigTransfer->getDoNotDecompoundTerms());

        if ($searchAnalyzerConfigTransfer->getAppliedIndexName() !== null) {
            $output->writeln(sprintf('  last applied: %s at %s', $searchAnalyzerConfigTransfer->getAppliedIndexName(), $searchAnalyzerConfigTransfer->getAppliedAt() ?? '-'));
        }

        return static::CODE_SUCCESS;
    }

    /**
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     * @param string $label
     * @param iterable<\Generated\Shared\Transfer\SearchAnalyzerConfigTermTransfer> $searchAnalyzerConfigTermTransfers
     */
    protected function printTermList(OutputInterface $output, string $label, iterable $searchAnalyzerConfigTermTransfers): void
    {
        $terms = [];

        foreach ($searchAnalyzerConfigTermTransfers as $searchAnalyzerConfigTermTransfer) {
            $terms[] = $searchAnalyzerConfigTermTransfer->getTerm();
        }

        $output->writeln(sprintf('  %s: %s', $label, $terms === [] ? '-' : implode(', ', $terms)));
    }
}
