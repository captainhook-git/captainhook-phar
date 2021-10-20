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
use Composer\Script\ScriptEvents;
use PharIo\ComposerDistributor\ConfiguredMediator;
use RuntimeException;

/**
 * Composer Plugin
 *
 * @package CaptainHook
 * @author  Sebastian Feldmann <sf@sebastian-feldmann.info>
 * @link    https://github.com/captainhookphp/captainhook
 * @since   Class available since Release 5.10.0
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
     * Detected dotGit file or directory
     *
     * @var \CaptainHook\Composer\DotGit
     */
    private $dotGit;

    /**
     * Detected CaptainHook executable
     *
     * @var string
     */
    private $executable;

    /**
     * Flag to determine to install Captain Hook after a package update or install
     *
     * @var bool
     */
    private $isPackageUpdate = false;

    /**
     * Return the path to the captainhook distributor configuration file
     *
     * @return string
     */
    protected function getDistributorConfig(): string
    {
        return __DIR__ . '/../distributor.xml';
    }

    /**
     * Return a list af all supported events and their actions
     *
     * @return array
     */
    public static function getSubscribedEvents()
    {
        $existingEvents = parent::getSubscribedEvents();

        return array_merge_recursive(
            $existingEvents,
            [
                ScriptEvents::POST_AUTOLOAD_DUMP => [
                    ['installHooksAfterPackageUpdate', 0]
                ]
            ]
        );
    }

    /**
     * On install or update install hooks
     *
     * @param  \Composer\Installer\PackageEvent $event
     * @throws \Exception
     */
    public function installOrUpdateFunction(PackageEvent $event): void
    {
        if ($this->isPluginDisabled()) {
            $this->getIO()->write('  <comment>plugin is disabled</comment>');
            return;
        }
        if ($this->isRunningOnCI()) {
            $this->getIO()->write('  <comment>disabling plugin due to CI-environment</comment>');
            return;
        }
        if ($this->isAdditionalWorktree()) {
            $this->getIO()->write('  <comment>no need to install hooks in additional worktree</comment>');
            return;
        }
        // download phar and check signature
        parent::installOrUpdateFunction($event);
        // try to configure and install hooks
        $this->configureHooks();
    }

    /**
     * Configure the installer
     *
     * @return void
     * @throws \Exception
     */
    public function configureHooks(): void
    {
        $this->isPackageUpdate = true;
        $this->getIO()->write('<info>CaptainHook</info>');

        $this->detectConfiguration();
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
    }

    /**
     * Install hooks to your .git/hooks directory
     */
    public function installHooksAfterPackageUpdate(): void
    {
        if (!$this->isPackageUpdate) {
            return;
        }
        $this->getIO()->write('  - Install hooks: ', false);
        $runner = new Captain($this->executable, $this->configuration, $this->dotGit->gitDirectory());
        $runner->execute(Captain::COMMAND_INSTALL, $this->getIO());
        $this->getIO()->write(('<comment> done</comment>'));
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

        $runner = new Captain($this->executable, $this->configuration, $this->dotGit->gitDirectory());
        $runner->execute(Captain::COMMAND_CONFIGURE, $this->getIO());
    }

    /**
     * Set the path to the CaptainHook configuration file
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
     *
     * @return void
     * @throws \RuntimeException
     */
    private function detectGitDir(): void
    {
        try {
            $this->dotGit = DotGit::searchInPath(getcwd());
        } catch (RuntimeException $e) {
            throw new RuntimeException($this->pluginErrorMessage($e->getMessage()));
        }
    }

    /**
     * Detects the captainhook binary
     *
     * @return void
     */
    private function detectCaptainExecutable(): void
    {
        $extra = $this->getComposer()->getPackage()->getExtra();
        if (isset($extra['captainhook']['exec'])) {
            $this->executable = (string) $extra['captainhook']['exec'];
            return;
        }

        $this->executable = $this->getComposer()->getConfig()->get('bin-dir') . '/captainhook';
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

    /**
     * Checks if this is a continuous integration (CI) run
     *
     * @return bool
     */
    private function isRunningOnCI(): bool
    {
        return getenv('CI') === 'true';
    }

    /**
     * Make sure we are not running inside an additional worktree
     *
     * @return bool
     */
    private function isAdditionalWorktree(): bool
    {
        $this->detectGitDir();
        return $this->dotGit->isAdditionalWorktree();
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
}
