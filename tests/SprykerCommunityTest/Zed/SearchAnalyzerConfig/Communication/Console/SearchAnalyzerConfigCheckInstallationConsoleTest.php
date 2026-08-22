<?php

/**
 * This file is part of the spryker-community/search-analyzer-config package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunityTest\Zed\SearchAnalyzerConfig\Communication\Console;

use Codeception\Test\Unit;
use SprykerCommunity\Zed\SearchAnalyzerConfig\Communication\Console\SearchAnalyzerConfigCheckInstallationConsole;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Deliberately hits this demoshop's OWN real project wiring for every check -- core namespace, the three
 * Propel tables, search-index-alias's own installedness, the rebuild-target plugin's loadability, whether
 * any scope has a staged config, and the project's navigation.xml -- same portability tradeoff every
 * sibling package's own CheckInstallationConsoleTest already accepts: this command exists specifically to
 * diagnose a REAL installation, a mocked facade would prove nothing about whether the project's own
 * wiring is actually correct. This demoshop is expected to be fully wired.
 *
 * @group SprykerCommunityTest
 * @group Zed
 * @group SearchAnalyzerConfig
 * @group Communication
 * @group Console
 * @group SearchAnalyzerConfigCheckInstallationConsoleTest
 * @group NeedsProject
 */
class SearchAnalyzerConfigCheckInstallationConsoleTest extends Unit
{
    public function testSucceedsAndReportsEveryCheckAgainstTheRealInstallation(): void
    {
        // Arrange
        $commandTester = $this->createCommandTester();

        // Act
        $exitCode = $commandTester->execute([]);

        // Assert
        $this->assertSame(SearchAnalyzerConfigCheckInstallationConsole::CODE_SUCCESS, $exitCode);
        $display = $commandTester->getDisplay();
        $this->assertStringContainsString('core namespace "SprykerCommunity" is registered', $display);
        $this->assertStringContainsString('table "spy_search_analyzer_config" is reachable', $display);
        $this->assertStringContainsString('table "spy_search_analyzer_config_term" is reachable', $display);
        $this->assertStringContainsString('table "spy_search_analyzer_config_revision" is reachable', $display);
        $this->assertStringContainsString('spryker-community/search-index-alias is installed', $display);
        $this->assertStringContainsString('rebuild-target plugin is loadable and correctly typed', $display);
        $this->assertStringContainsString('navigation entry is registered in config/Zed/navigation.xml', $display);
        $this->assertStringContainsString('Everything checkable from the CLI is in place.', $display);
    }

    protected function createCommandTester(): CommandTester
    {
        $console = new SearchAnalyzerConfigCheckInstallationConsole();

        $application = new Application();
        $application->add($console);

        $command = $application->find(SearchAnalyzerConfigCheckInstallationConsole::COMMAND_NAME);

        return new CommandTester($command);
    }
}
