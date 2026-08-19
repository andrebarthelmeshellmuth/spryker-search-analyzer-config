<?php

/**
 * This file is part of the spryker-community/search-analyzer-config package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchAnalyzerConfig\Communication\Console;

use Orm\Zed\SearchAnalyzerConfig\Persistence\SpySearchAnalyzerConfigQuery;
use Orm\Zed\SearchAnalyzerConfig\Persistence\SpySearchAnalyzerConfigRevisionQuery;
use Orm\Zed\SearchAnalyzerConfig\Persistence\SpySearchAnalyzerConfigTermQuery;
use Spryker\Shared\Config\Config;
use Spryker\Shared\Kernel\KernelConstants;
use Spryker\Zed\Kernel\Communication\Console\Console;
use SprykerCommunity\Zed\SearchAnalyzerConfig\Communication\Plugin\SearchIndexAlias\SearchAnalyzerConfigTargetIndexSettingsExpanderPlugin;
use SprykerCommunity\Zed\SearchIndexAlias\Dependency\Plugin\TargetIndexSettingsExpanderPluginInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

/**
 * Diagnoses a search-analyzer-config installation.
 *
 * Same failure shape as every sibling package: a forgotten wire-up produces no error, a staged config
 * simply never reaches a rebuilt index. This checks every prerequisite reachable from the CLI and names
 * the exact remedy for whatever is wrong.
 *
 * As of the Zed GUI (Overview/Edit/Copy/Preview/History pages), this also checks the project's own
 * `config/Zed/navigation.xml` for the top-level nav entry -- back-office ACL itself needs no package-side
 * check, since Spryker's own Acl module gates every Zed module identically regardless of what's installed.
 * What this CANNOT check from the CLI: whether the host project's own SearchIndexAliasDependencyProvider
 * override actually returns SearchAnalyzerConfigTargetIndexSettingsExpanderPlugin from
 * getTargetIndexSettingsExpanderPlugins() -- that override lives in the adopting project's own
 * `Pyz`-or-equivalent namespace, which this package cannot reference without breaking portability to
 * every other adopter's namespace (same limitation documented in
 * {@see \SprykerCommunity\Zed\VariantFacets\Communication\Console\VariantFacetsCheckInstallationConsole}).
 *
 * @method \SprykerCommunity\Zed\SearchAnalyzerConfig\Business\SearchAnalyzerConfigFacadeInterface getFacade()
 * @method \SprykerCommunity\Zed\SearchAnalyzerConfig\Communication\SearchAnalyzerConfigCommunicationFactory getFactory()
 */
class SearchAnalyzerConfigCheckInstallationConsole extends Console
{
    /**
     * @var string
     */
    public const COMMAND_NAME = 'search-analyzer-config:check-installation';

    /**
     * @var string
     */
    public const COMMAND_DESCRIPTION = 'Diagnoses a search-analyzer-config installation: core namespace, the Propel tables, and whether the search-index-alias rebuild-target plugin is loadable and correctly typed.';

    /**
     * @var string
     */
    protected const CORE_NAMESPACE = 'SprykerCommunity';

    /**
     * @var array<string>
     */
    protected array $failures = [];

    /**
     * @var array<string>
     */
    protected array $warnings = [];

    protected function configure(): void
    {
        $this->setName(static::COMMAND_NAME);
        $this->setDescription(static::COMMAND_DESCRIPTION);

        parent::configure();
    }

    /**
     * @phpcsSuppress SlevomatCodingStandard.Functions.UnusedParameter $input is mandated by the Console base class.
     *
     * @param \Symfony\Component\Console\Input\InputInterface $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->checkCoreNamespace($output);
        $this->checkPropelTablesExist($output);
        $this->checkSearchIndexAliasInstalled($output);
        $this->checkRebuildTargetPlugin($output);
        $this->checkAnyScopeStaged($output);
        $this->checkNavigationRegistered($output);

        $output->writeln('');

        foreach ($this->warnings as $warning) {
            $output->writeln(sprintf('<comment>! %s</comment>', $warning));
        }

        if ($this->failures !== []) {
            foreach ($this->failures as $failure) {
                $output->writeln(sprintf('<error>✗ %s</error>', $failure));
            }

            return static::CODE_ERROR;
        }

        $output->writeln('<info>Everything checkable from the CLI is in place.</info>');
        $output->writeln('Not verifiable from here — confirm by hand in your own project:');
        $output->writeln('  - that Pyz\Zed\SearchIndexAlias\SearchIndexAliasDependencyProvider (or your project\'s equivalent)');
        $output->writeln('    actually returns SearchAnalyzerConfigTargetIndexSettingsExpanderPlugin from');
        $output->writeln('    getTargetIndexSettingsExpanderPlugins() — a forgotten registration fails silently: a staged');
        $output->writeln('    config just never reaches a rebuilt index');
        $output->writeln('  - that any role that should be able to reach the "Search Analyzer Config" back-office pages has');
        $output->writeln('    ACL access to the "search-analyzer-config" module — a default install grants this to any');
        $output->writeln('    unrestricted (root-style) role automatically, same as every other Zed module');

        return static::CODE_SUCCESS;
    }

    /**
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     */
    protected function checkCoreNamespace(OutputInterface $output): void
    {
        $coreNamespaces = Config::get(KernelConstants::CORE_NAMESPACES, []);

        if (in_array(static::CORE_NAMESPACE, $coreNamespaces, true)) {
            $output->writeln(sprintf('<info>✓</info> core namespace "%s" is registered', static::CORE_NAMESPACE));

            return;
        }

        $this->failures[] = sprintf(
            'Core namespace "%s" is NOT registered. Add it to KernelConstants::CORE_NAMESPACES in config/Shared/config_default.php — without it Spryker cannot resolve any of this package\'s classes.',
            static::CORE_NAMESPACE,
        );
    }

    /**
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     */
    protected function checkPropelTablesExist(OutputInterface $output): void
    {
        $tables = [
            'spy_search_analyzer_config' => SpySearchAnalyzerConfigQuery::class,
            'spy_search_analyzer_config_term' => SpySearchAnalyzerConfigTermQuery::class,
            'spy_search_analyzer_config_revision' => SpySearchAnalyzerConfigRevisionQuery::class,
        ];

        foreach ($tables as $tableName => $queryClass) {
            try {
                (new $queryClass())->count();
            } catch (Throwable) {
                $this->failures[] = sprintf(
                    'The "%s" table is missing or unreachable. Run `vendor/bin/console propel:install` (or propel:migrate) after installing this package.',
                    $tableName,
                );

                continue;
            }

            $output->writeln(sprintf('<info>✓</info> table "%s" is reachable', $tableName));
        }
    }

    /**
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     */
    protected function checkSearchIndexAliasInstalled(OutputInterface $output): void
    {
        if (interface_exists(TargetIndexSettingsExpanderPluginInterface::class)) {
            $output->writeln('<info>✓</info> spryker-community/search-index-alias is installed');

            return;
        }

        $this->failures[] = 'spryker-community/search-index-alias is NOT installed. This package has nothing to materialize a staged config into without it — require it via composer.';
    }

    /**
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     */
    protected function checkRebuildTargetPlugin(OutputInterface $output): void
    {
        if (!interface_exists(TargetIndexSettingsExpanderPluginInterface::class)) {
            $this->warnings[] = 'Skipping the rebuild-target plugin check — search-index-alias is not installed (see above).';

            return;
        }

        if (!class_exists(SearchAnalyzerConfigTargetIndexSettingsExpanderPlugin::class)) {
            $this->failures[] = 'SearchAnalyzerConfigTargetIndexSettingsExpanderPlugin class could not be autoloaded.';

            return;
        }

        $implementedInterfaces = class_implements(SearchAnalyzerConfigTargetIndexSettingsExpanderPlugin::class);

        if (!in_array(TargetIndexSettingsExpanderPluginInterface::class, $implementedInterfaces, true)) {
            $this->failures[] = 'SearchAnalyzerConfigTargetIndexSettingsExpanderPlugin does not implement TargetIndexSettingsExpanderPluginInterface — this points at a version mismatch between this package and the installed search-index-alias version.';

            return;
        }

        $output->writeln('<info>✓</info> rebuild-target plugin is loadable and correctly typed');
    }

    /**
     * Purely informational — a fresh install with zero staged scopes is expected, not broken (every
     * SearchAnalyzerConfigTargetIndexSettingsExpanderPlugin::expand() call returns settings unchanged
     * until a first scope is saved). Flagged as a warning only so an operator who expected an existing
     * config to already be staged notices it isn't, rather than discovering it via a rebuild that quietly
     * changes nothing.
     *
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     */
    protected function checkAnyScopeStaged(OutputInterface $output): void
    {
        try {
            $scopeCount = (new SpySearchAnalyzerConfigQuery())->count();
        } catch (Throwable) {
            return;
        }

        if ($scopeCount === 0) {
            $this->warnings[] = 'No scope has a staged config yet (0 rows in spy_search_analyzer_config). This is normal on a fresh install — every rebuild will render settings unchanged until a config is saved via SearchAnalyzerConfigFacade::save().';

            return;
        }

        $output->writeln(sprintf('<info>✓</info> %d scope(s) have a staged config', $scopeCount));
    }

    /**
     * Simple presence check against the project's own config/Zed/navigation.xml -- this package's own
     * navigation.xml (the source-of-truth copy this project-side entry must match) ships one wrapper
     * page (`search-analyzer-config-gui`) with four hidden leaf pages reachable by URL alone, not
     * separate nav entries, so unlike search-index-alias's own multi-page diff this only needs to confirm
     * the one wrapper entry exists.
     *
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     */
    protected function checkNavigationRegistered(OutputInterface $output): void
    {
        $projectNavigationXmlPath = APPLICATION_ROOT_DIR . '/config/Zed/navigation.xml';

        if (!is_file($projectNavigationXmlPath)) {
            $this->warnings[] = 'Could not find config/Zed/navigation.xml to confirm the nav entry. Confirm by hand that "Search Analyzer Config" appears in the Zed sidebar.';

            return;
        }

        $projectNavigationXmlContents = (string)file_get_contents($projectNavigationXmlPath);

        if (!str_contains($projectNavigationXmlContents, '<search-analyzer-config-gui>')) {
            $this->failures[] = 'No "search-analyzer-config-gui" entry found in config/Zed/navigation.xml -- copy this package\'s own Communication/navigation.xml wrapper entry into your project\'s navigation.xml, then run `console navigation:build-cache`.';

            return;
        }

        $output->writeln('<info>✓</info> navigation entry is registered in config/Zed/navigation.xml');
    }
}
