<?php

/**
 * This file is part of the spryker-community/search-analyzer-config package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Zed\SearchAnalyzerConfig\Communication\Console;

use Codeception\Test\Unit;
use Generated\Shared\Transfer\SearchAnalyzerConfigTransfer;
use Generated\Shared\Transfer\SearchIndexScopeTransfer;
use SprykerCommunity\Zed\SearchAnalyzerConfig\Business\SearchAnalyzerConfigFacade;
use SprykerCommunity\Zed\SearchAnalyzerConfig\Communication\Console\SearchAnalyzerConfigCopyConsole;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @group SprykerCommunityTest
 * @group Zed
 * @group SearchAnalyzerConfig
 * @group Communication
 * @group Console
 * @group SearchAnalyzerConfigCopyConsoleTest
 * @group Portable
 */
class SearchAnalyzerConfigCopyConsoleTest extends Unit
{
    protected const MANAGED_SCOPES_ARGUMENTS = ['source' => 'page', 'store' => 'DE', 'target-source' => 'page', 'target-store' => 'AT'];

    public function testFailsWhenTheSourceScopeIsNotManaged(): void
    {
        // Arrange
        $commandTester = $this->createCommandTester($this->buildFacadeMock([]));

        // Act
        $exitCode = $commandTester->execute(static::MANAGED_SCOPES_ARGUMENTS);

        // Assert
        $this->assertSame(SearchAnalyzerConfigCopyConsole::CODE_ERROR, $exitCode);
        $this->assertStringContainsString('"page" / "DE" is not a search-index-alias managed scope.', $commandTester->getDisplay());
    }

    public function testFailsWhenTheTargetScopeIsNotManaged(): void
    {
        // Arrange
        $managedScopes = [(new SearchIndexScopeTransfer())->setSourceIdentifier('page')->setStoreName('DE')];
        $commandTester = $this->createCommandTester($this->buildFacadeMock($managedScopes));

        // Act
        $exitCode = $commandTester->execute(static::MANAGED_SCOPES_ARGUMENTS);

        // Assert
        $this->assertSame(SearchAnalyzerConfigCopyConsole::CODE_ERROR, $exitCode);
        $this->assertStringContainsString('"page" / "AT" is not a search-index-alias managed scope.', $commandTester->getDisplay());
    }

    public function testFailsAndReportsEveryErrorWhenTheCopyItselfFails(): void
    {
        // Arrange
        $managedScopes = [
            (new SearchIndexScopeTransfer())->setSourceIdentifier('page')->setStoreName('DE'),
            (new SearchIndexScopeTransfer())->setSourceIdentifier('page')->setStoreName('AT'),
        ];
        $facadeMock = $this->buildFacadeMock($managedScopes);
        $facadeMock->method('copy')->with('page', 'page', 'DE', 'AT', 'console')->willReturn(['No config staged for source "page" / store "DE".']);

        $commandTester = $this->createCommandTester($facadeMock);

        // Act
        $exitCode = $commandTester->execute(static::MANAGED_SCOPES_ARGUMENTS);

        // Assert
        $this->assertSame(SearchAnalyzerConfigCopyConsole::CODE_ERROR, $exitCode);
        $this->assertStringContainsString('No config staged for source "page" / store "DE".', $commandTester->getDisplay());
    }

    public function testReportsTheNewRevisionOnSuccess(): void
    {
        // Arrange
        $managedScopes = [
            (new SearchIndexScopeTransfer())->setSourceIdentifier('page')->setStoreName('DE'),
            (new SearchIndexScopeTransfer())->setSourceIdentifier('page')->setStoreName('AT'),
        ];
        $facadeMock = $this->buildFacadeMock($managedScopes);
        $facadeMock->method('copy')->with('page', 'page', 'DE', 'AT', 'console')->willReturn([]);
        $facadeMock->method('findByScope')->with('page', 'AT')->willReturn((new SearchAnalyzerConfigTransfer())->setRevision(4));

        $commandTester = $this->createCommandTester($facadeMock);

        // Act
        $exitCode = $commandTester->execute(static::MANAGED_SCOPES_ARGUMENTS);

        // Assert
        $this->assertSame(SearchAnalyzerConfigCopyConsole::CODE_SUCCESS, $exitCode);
        $this->assertStringContainsString('Copied "page"/"DE" onto "page"/"AT" (new revision 4).', $commandTester->getDisplay());
    }

    /**
     * @param array<\Generated\Shared\Transfer\SearchIndexScopeTransfer> $managedScopes
     */
    protected function buildFacadeMock(array $managedScopes): SearchAnalyzerConfigFacade
    {
        $facadeMock = $this->getMockBuilder(SearchAnalyzerConfigFacade::class)
            ->onlyMethods(['getManagedScopes', 'copy', 'findByScope'])
            ->getMock();
        $facadeMock->method('getManagedScopes')->willReturn($managedScopes);

        return $facadeMock;
    }

    protected function createCommandTester(SearchAnalyzerConfigFacade $facadeMock): CommandTester
    {
        $console = new SearchAnalyzerConfigCopyConsole();
        $console->setFacade($facadeMock);

        $application = new Application();
        $application->add($console);

        $command = $application->find(SearchAnalyzerConfigCopyConsole::COMMAND_NAME);

        return new CommandTester($command);
    }
}
