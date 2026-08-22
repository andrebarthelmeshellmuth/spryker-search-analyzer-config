<?php

/**
 * This file is part of the spryker-community/search-analyzer-config package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Zed\SearchAnalyzerConfig\Communication\Console;

use Codeception\Test\Unit;
use Generated\Shared\Transfer\SearchIndexRolloutTransfer;
use SprykerCommunity\Zed\SearchAnalyzerConfig\Business\SearchAnalyzerConfigFacade;
use SprykerCommunity\Zed\SearchAnalyzerConfig\Communication\Console\SearchAnalyzerConfigApplyConsole;
use SprykerCommunity\Zed\SearchIndexAlias\Persistence\Exception\ConcurrentRolloutException;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @group SprykerCommunityTest
 * @group Zed
 * @group SearchAnalyzerConfig
 * @group Communication
 * @group Console
 * @group SearchAnalyzerConfigApplyConsoleTest
 * @group Portable
 */
class SearchAnalyzerConfigApplyConsoleTest extends Unit
{
    public function testReportsTheRolloutOnSuccess(): void
    {
        // Arrange
        $rolloutTransfer = (new SearchIndexRolloutTransfer())->setIdSearchIndexRollout(7)->setTargetIndexName('myshop_de_page_20260101120000');
        $facadeMock = $this->buildFacadeMock();
        $facadeMock->method('requestRebuild')->with('page', 'DE', 'console')->willReturn($rolloutTransfer);

        $commandTester = $this->createCommandTester($facadeMock);

        // Act
        $exitCode = $commandTester->execute(['source' => 'page', 'store' => 'DE']);

        // Assert
        $this->assertSame(SearchAnalyzerConfigApplyConsole::CODE_SUCCESS, $exitCode);
        $this->assertStringContainsString('Rebuild 7 requested for "page" / "DE"', $commandTester->getDisplay());
    }

    public function testFailsWhenTheScopeIsNotManaged(): void
    {
        // Arrange
        $facadeMock = $this->buildFacadeMock();
        $facadeMock->method('requestRebuild')->with('page', 'DE', 'console')->willReturn(null);

        $commandTester = $this->createCommandTester($facadeMock);

        // Act
        $exitCode = $commandTester->execute(['source' => 'page', 'store' => 'DE']);

        // Assert
        $this->assertSame(SearchAnalyzerConfigApplyConsole::CODE_ERROR, $exitCode);
        $this->assertStringContainsString('is not a search-index-alias managed scope', $commandTester->getDisplay());
    }

    public function testFailsWhenAnotherRolloutIsAlreadyInProgress(): void
    {
        // Arrange
        $facadeMock = $this->buildFacadeMock();
        $facadeMock->method('requestRebuild')->willThrowException(new ConcurrentRolloutException('already rolling out'));

        $commandTester = $this->createCommandTester($facadeMock);

        // Act
        $exitCode = $commandTester->execute(['source' => 'page', 'store' => 'DE']);

        // Assert
        $this->assertSame(SearchAnalyzerConfigApplyConsole::CODE_ERROR, $exitCode);
        $this->assertStringContainsString('already rolling out', $commandTester->getDisplay());
    }

    protected function buildFacadeMock(): SearchAnalyzerConfigFacade
    {
        return $this->getMockBuilder(SearchAnalyzerConfigFacade::class)
            ->onlyMethods(['requestRebuild'])
            ->getMock();
    }

    protected function createCommandTester(SearchAnalyzerConfigFacade $facadeMock): CommandTester
    {
        $console = new SearchAnalyzerConfigApplyConsole();
        $console->setFacade($facadeMock);

        $application = new Application();
        $application->add($console);

        $command = $application->find(SearchAnalyzerConfigApplyConsole::COMMAND_NAME);

        return new CommandTester($command);
    }
}
