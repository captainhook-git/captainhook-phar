<?php

/**
 * This file is part of CaptainHook
 *
 * (c) Sebastian Feldmann <sf@sebastian-feldmann.info>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace CaptainHook\Composer;

use Composer\Installer\PackageEvent;
use Composer\Script\Event;
use PharIo\ComposerDistributor\ConfiguredMediator;
use RuntimeException;

/**
 * Composer Plugin
 *
 * @package CaptainHook
 * @author  Sebastian Feldmann <sf@sebastian-feldmann.info>
 * @link    https://github.com/captainhookphp/captainhook
 * @since   Class available since Release 6.0.0
 */
class Plugin extends ConfiguredMediator
{
    /**
     * Detected CaptainHook configuration file
     *
     * @var string
     */
    private $configuration;

    /**
     * Detected git directory
     *
     * @var string
     */
    private $gitDirectory;

    /**
     * Detected CaptainHook executable
     *
     * @var mixed
     */
    private $executable;

    protected function getDistributorConfig(): string
    {
        return __DIR__ . '/../distributor.xml';
    }

    /**
     * On install of update install hooks
     *
     * @param  \Composer\Installer\PackageEvent $event
     * @throws \Exception
     */
    public function installOrUpdateFunction(PackageEvent $event): void
    {
        // download phar and check signature
        parent::installOrUpdateFunction($event);
        // try to configure and install hooks
        $this->installHooks();
    }

    /**
     * Run the installer
     *
     * @return void
     * @throws \Exception
     */
    public function installHooks(): void
    {
        $this->getIO()->write('<info>CaptainHook</info>');

        if ($this->isPluginDisabled()) {
            $this->getIO()->write('  <comment>plugin is disabled</comment>');
            return;
        }

        if (getenv('CI') === 'true') {
            $this->getIO()->write(' <comment>disabling plugin due to CI-environment</comment>');
            return;
        }

        $this->detectConfiguration();
        $this->detectGitDir();
        $this->detectCaptainExecutable();

        if (!file_exists($this->executable)) {
            $this->getIO()->write(
                '<comment>CaptainHook executable not found</comment>' . PHP_EOL .
                PHP_EOL .
                'If you are uninstalling CaptainHook, we are sad seeing you go, ' .
                'but we would appreciate your feedback on your experience.' . PHP_EOL .
                'Just go to https://github.com/CaptainHookPhp/captainhook/issues to leave your feedback' . PHP_EOL .
                '<comment>WARNING: Don\'t forget to deactivate the hooks in your .git/hooks directory.</comment>' .
                PHP_EOL
            );
            return;
        }

        $this->configure();
        $this->install();
    }

    /**
     * Create captainhook.json file if it does not exist
     */
    private function configure(): void
    {
        $this->getIO()->write('  - Detect configuration: ', false);
        if (file_exists($this->configuration)) {
            $this->getIO()->write('<comment>found ' . $this->configuration . '</comment>');
            return;
        }

        $this->getIO()->write('<comment>no configuration found</comment>');

        $runner = new Captain($this->executable, $this->configuration, $this->gitDirectory);
        $runner->execute(Captain::COMMAND_CONFIGURE, $this->getIO());

    }

    /**
     * Install hooks to your .git/hooks directory
     */
    private function install(): void
    {
        $this->getIO()->write('  - Install hooks: ', false);
        $runner = new Captain($this->executable, $this->configuration, $this->gitDirectory);
        $runner->execute(Captain::COMMAND_INSTALL, $this->getIO());
        $this->getIO()->write(('<comment> done</comment>'));
    }

    /**
     * Return path to the CaptainHook configuration file
     *
     * @return void
     */
    private function detectConfiguration(): void
    {
        $extra               = $this->getComposer()->getPackage()->getExtra();
        $this->configuration = getcwd() . '/' . ($extra['captainhook']['config'] ?? 'captainhook.json');
    }

    /**
     * Search for the git repository to store the hooks in

     * @return void
     * @throws \RuntimeException
     */
    private function detectGitDir(): void
    {
        $path = getcwd();

        while (file_exists($path)) {
            $possibleGitDir = $path . '/.git';
            if (is_dir($possibleGitDir)) {
                $this->gitDirectory = $possibleGitDir;
                return;
            }

            // if we checked the root directory already, break to prevent endless loop
            if ($path === dirname($path)) {
                break;
            }

            $path = dirname($path);
        }
        throw new RuntimeException($this->pluginErrorMessage('git directory not found'));
    }

    /**
     * Creates a nice formatted error message
     *
     * @param  string $reason
     * @return string
     */
    private function pluginErrorMessage(string $reason): string
    {
        return 'Shiver me timbers! CaptainHook could not install yer git hooks! (' . $reason . ')';
    }

    /**
     *
     */
    private function detectCaptainExecutable(): void
    {
        $extra = $this->getComposer()->getPackage()->getExtra();
        if (isset($extra['captainhook']['exec'])) {
            $this->executable = $extra['captainhook']['exec'];
            return;
        }

        $this->executable = (string) $this->getComposer()->getConfig()->get('bin-dir') . '/captainhook';
    }

    /**
     * Check if the plugin is disabled
     *
     * @return bool
     */
    private function isPluginDisabled(): bool
    {
        $extra = $this->getComposer()->getPackage()->getExtra();
        return (bool) ($extra['captainhook']['disable-plugin'] ?? false);
    }
}
