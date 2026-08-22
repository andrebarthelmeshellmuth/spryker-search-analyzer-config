<?php

/**
 * This file is part of the spryker-community/search-analyzer-config package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Zed\SearchAnalyzerConfig\Communication\Console;

use Codeception\Test\Unit;
use Generated\Shared\Transfer\SearchAnalyzerConfigTermTransfer;
use Generated\Shared\Transfer\SearchAnalyzerConfigTransfer;
use SprykerCommunity\Zed\SearchAnalyzerConfig\Business\SearchAnalyzerConfigFacade;
use SprykerCommunity\Zed\SearchAnalyzerConfig\Communication\Console\SearchAnalyzerConfigShowConsole;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @group SprykerCommunityTest
 * @group Zed
 * @group SearchAnalyzerConfig
 * @group Communication
 * @group Console
 * @group SearchAnalyzerConfigShowConsoleTest
 * @group Portable
 */
class SearchAnalyzerConfigShowConsoleTest extends Unit
{
    public function testReportsNoConfigStagedWhenNoneExists(): void
    {
        // Arrange
        $commandTester = $this->createCommandTester(null);

        // Act
        $exitCode = $commandTester->execute(['source' => 'page', 'store' => 'DE']);

        // Assert
        $this->assertSame(SearchAnalyzerConfigShowConsole::CODE_SUCCESS, $exitCode);
        $this->assertStringContainsString('No config staged for source "page" / store "DE".', $commandTester->getDisplay());
    }

    public function testDumpsEveryFieldAndTermListWhenAConfigIsStaged(): void
    {
        // Arrange
        $configTransfer = (new SearchAnalyzerConfigTransfer())
            ->setRevision(3)
            ->setAppliedRevision(2)
            ->setStemmerLanguage('german')
            ->setNormalizationFilter('lowercase')
            ->setStopwordsMode('builtin')
            ->setStopwordsBuiltinLanguage('german')
            ->setDecompoundEnabled(true)
            ->addDecompoundWord((new SearchAnalyzerConfigTermTransfer())->setTerm('kabel'))
            ->addSynonym((new SearchAnalyzerConfigTermTransfer())->setTerm('cable => kabel'));

        $commandTester = $this->createCommandTester($configTransfer);

        // Act
        $exitCode = $commandTester->execute(['source' => 'page', 'store' => 'DE']);

        // Assert
        $this->assertSame(SearchAnalyzerConfigShowConsole::CODE_SUCCESS, $exitCode);
        $display = $commandTester->getDisplay();
        $this->assertStringContainsString('revision 3, applied revision 2', $display);
        $this->assertStringContainsString('stemmerLanguage: german', $display);
        $this->assertStringContainsString('decompoundWords: kabel', $display);
        $this->assertStringContainsString('synonyms: cable => kabel', $display);
    }

    protected function createCommandTester(?SearchAnalyzerConfigTransfer $configTransfer): CommandTester
    {
        $facadeMock = $this->getMockBuilder(SearchAnalyzerConfigFacade::class)
            ->onlyMethods(['findByScope'])
            ->getMock();
        $facadeMock->method('findByScope')->with('page', 'DE')->willReturn($configTransfer);

        $console = new SearchAnalyzerConfigShowConsole();
        $console->setFacade($facadeMock);

        $application = new Application();
        $application->add($console);

        $command = $application->find(SearchAnalyzerConfigShowConsole::COMMAND_NAME);

        return new CommandTester($command);
    }
}
