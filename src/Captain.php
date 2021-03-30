<?php

/**
 * This file is part of CaptainHook
 *
 * (c) Sebastian Feldmann <sf@sebastian-feldmann.info>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace CaptainHook\Composer;

use Composer\IO\IOInterface;
use RuntimeException;

class Captain
{
    public const COMMAND_CONFIGURE = 'configure';
    public const COMMAND_INSTALL   = 'install';

    /**
     * Path to CaptainHook phar file
     *
     * @var string
     */
    private $executable;

    /**
     * Path to the CaptainHook configuration file
     *
     * @var string
     */
    private $configuration;

    /**
     * Path the the .git directory
     *
     * @var string
     */
    private $gitDirectory;

    /**
     * Captain constructor
     *
     * @param string $executable
     * @param string $configuration
     * @param string $gitDirectory
     */
    public function __construct(string $executable, string $configuration, string $gitDirectory)
    {
        $this->executable    = $executable;
        $this->configuration = $configuration;
        $this->gitDirectory  = $gitDirectory;
    }

    public function execute(string $command, IOInterface $io)
    {
        // Respect composer CLI settings
        $ansi        = $io->isDecorated() ? ' --ansi' : ' --no-ansi';
        $interaction = $io->isInteractive() ? '' : ' --no-interaction';

        // captainhook config and repository settings
        $configuration  = ' -c ' . escapeshellarg($this->configuration);
        $repository     = $command === self::COMMAND_INSTALL ? ' -g ' . escapeshellarg($this->gitDirectory) : '';
        $skip           = $command === self::COMMAND_INSTALL ? ' -s' : '';
        $executable     = str_replace(' ', '\\ ', $this->executable);

        // sub process settings
        $cmd   = $executable . ' ' . $command . $ansi . $interaction . $skip . $configuration . $repository;
        $pipes = [];
        $spec  = [
            0 => ['file', 'php://stdin', 'r'],
            1 => ['file', 'php://stdout', 'w'],
            2 => ['file', 'php://stderr', 'w'],
        ];

        $process = @proc_open($cmd, $spec, $pipes);

        if ($io->isVerbose()) {
            $io->write('Running process : ' . $cmd);
        }
        if (!is_resource($process)) {
            throw new RuntimeException('no-process');
        }

        // Loop on process until it exits normally.
        do {
            $status = proc_get_status($process);
        } while ($status && $status['running']);

        $exitCode = $status['exitcode'] ?? -1;
        proc_close($process);
        if ($exitCode !== 0) {
            $io->writeError('installation process failed');
        }
    }
}
