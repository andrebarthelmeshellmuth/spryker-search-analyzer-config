<?php

/**
 * This file is part of the spryker-community/search-analyzer-config package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Zed\SearchAnalyzerConfig\Communication\Console;

use Codeception\Test\Unit;
use Generated\Shared\Transfer\SearchAnalyzerConfigTransfer;
use SprykerCommunity\Zed\SearchAnalyzerConfig\Business\Exception\SearchAnalyzerConfigInvalidTermException;
use SprykerCommunity\Zed\SearchAnalyzerConfig\Business\SearchAnalyzerConfigFacade;
use SprykerCommunity\Zed\SearchAnalyzerConfig\Communication\Console\SearchAnalyzerConfigExportSchemaConsole;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @group SprykerCommunityTest
 * @group Zed
 * @group SearchAnalyzerConfig
 * @group Communication
 * @group Console
 * @group SearchAnalyzerConfigExportSchemaConsoleTest
 * @group Portable
 */
class SearchAnalyzerConfigExportSchemaConsoleTest extends Unit
{
    protected const ARGUMENTS = ['source' => 'page', 'store' => 'DE', 'target-analyzer' => 'fulltext_search_analyzer'];

    public function testFailsWhenNoConfigIsStagedForTheScope(): void
    {
        // Arrange
        $facadeMock = $this->buildFacadeMock();
        $facadeMock->method('findByScope')->with('page', 'DE')->willReturn(null);

        $commandTester = $this->createCommandTester($facadeMock);

        // Act
        $exitCode = $commandTester->execute(static::ARGUMENTS);

        // Assert
        $this->assertSame(SearchAnalyzerConfigExportSchemaConsole::CODE_ERROR, $exitCode);
        $this->assertStringContainsString('No config staged for source "page" / store "DE" -- nothing to export.', $commandTester->getDisplay());
    }

    public function testRendersTheAnalysisFragmentAsJsonOnSuccess(): void
    {
        // Arrange
        $facadeMock = $this->buildFacadeMock();
        $facadeMock->method('findByScope')->with('page', 'DE')->willReturn(new SearchAnalyzerConfigTransfer());
        $facadeMock->method('renderIntoSettings')->willReturn(['analysis' => ['filter' => ['sac_normalization' => ['type' => 'lowercase']]]]);

        $commandTester = $this->createCommandTester($facadeMock);

        // Act
        $exitCode = $commandTester->execute(static::ARGUMENTS);

        // Assert
        $this->assertSame(SearchAnalyzerConfigExportSchemaConsole::CODE_SUCCESS, $exitCode);
        $this->assertStringContainsString('"sac_normalization"', $commandTester->getDisplay());
    }

    public function testFailsWhenAnInvalidTermIsStaged(): void
    {
        // Arrange
        $facadeMock = $this->buildFacadeMock();
        $facadeMock->method('findByScope')->with('page', 'DE')->willReturn(new SearchAnalyzerConfigTransfer());
        $facadeMock->method('renderIntoSettings')->willThrowException(new SearchAnalyzerConfigInvalidTermException('bad term'));

        $commandTester = $this->createCommandTester($facadeMock);

        // Act
        $exitCode = $commandTester->execute(static::ARGUMENTS);

        // Assert
        $this->assertSame(SearchAnalyzerConfigExportSchemaConsole::CODE_ERROR, $exitCode);
        $this->assertStringContainsString('bad term', $commandTester->getDisplay());
    }

    protected function buildFacadeMock(): SearchAnalyzerConfigFacade
    {
        return $this->getMockBuilder(SearchAnalyzerConfigFacade::class)
            ->onlyMethods(['findByScope', 'renderIntoSettings'])
            ->getMock();
    }

    protected function createCommandTester(SearchAnalyzerConfigFacade $facadeMock): CommandTester
    {
        $console = new SearchAnalyzerConfigExportSchemaConsole();
        $console->setFacade($facadeMock);

        $application = new Application();
        $application->add($console);

        $command = $application->find(SearchAnalyzerConfigExportSchemaConsole::COMMAND_NAME);

        return new CommandTester($command);
    }
}
