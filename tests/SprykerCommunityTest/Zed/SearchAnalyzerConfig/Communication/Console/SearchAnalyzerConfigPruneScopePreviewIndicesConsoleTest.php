<?php

/**
 * This file is part of the spryker-community/search-analyzer-config package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Zed\SearchAnalyzerConfig\Communication\Console;

use Codeception\Test\Unit;
use SprykerCommunity\Zed\SearchAnalyzerConfig\Business\SearchAnalyzerConfigFacade;
use SprykerCommunity\Zed\SearchAnalyzerConfig\Communication\Console\SearchAnalyzerConfigPruneScopePreviewIndicesConsole;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @group SprykerCommunityTest
 * @group Zed
 * @group SearchAnalyzerConfig
 * @group Communication
 * @group Console
 * @group SearchAnalyzerConfigPruneScopePreviewIndicesConsoleTest
 * @group Portable
 */
class SearchAnalyzerConfigPruneScopePreviewIndicesConsoleTest extends Unit
{
    public function testReportsNoOrphanedIndexWhenNoneWasDeleted(): void
    {
        // Arrange
        $commandTester = $this->createCommandTester([]);

        // Act
        $exitCode = $commandTester->execute([]);

        // Assert
        $this->assertSame(SearchAnalyzerConfigPruneScopePreviewIndicesConsole::CODE_SUCCESS, $exitCode);
        $this->assertStringContainsString('No orphaned preview index found.', $commandTester->getDisplay());
    }

    public function testReportsEveryDeletedIndexName(): void
    {
        // Arrange
        $commandTester = $this->createCommandTester(['myshop_de_page_preview_1', 'myshop_de_page_preview_2']);

        // Act
        $exitCode = $commandTester->execute([]);

        // Assert
        $this->assertSame(SearchAnalyzerConfigPruneScopePreviewIndicesConsole::CODE_SUCCESS, $exitCode);
        $display = $commandTester->getDisplay();
        $this->assertStringContainsString('Deleted orphaned preview index "myshop_de_page_preview_1".', $display);
        $this->assertStringContainsString('Deleted orphaned preview index "myshop_de_page_preview_2".', $display);
    }

    /**
     * @param array<string> $deletedIndexNames
     */
    protected function createCommandTester(array $deletedIndexNames): CommandTester
    {
        $facadeMock = $this->getMockBuilder(SearchAnalyzerConfigFacade::class)
            ->onlyMethods(['pruneOrphanedPreviewIndices'])
            ->getMock();
        $facadeMock->method('pruneOrphanedPreviewIndices')->willReturn($deletedIndexNames);

        $console = new SearchAnalyzerConfigPruneScopePreviewIndicesConsole();
        $console->setFacade($facadeMock);

        $application = new Application();
        $application->add($console);

        $command = $application->find(SearchAnalyzerConfigPruneScopePreviewIndicesConsole::COMMAND_NAME);

        return new CommandTester($command);
    }
}
