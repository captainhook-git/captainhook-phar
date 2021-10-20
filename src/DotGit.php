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

use RuntimeException;

/**
 * DotGit
 *
 * @package CaptainHook
 * @author  Sebastian Feldmann <sf@sebastian-feldmann.info>
 * @link    https://github.com/captainhookphp/captainhook
 * @since   Class available since Release 5.10.0
 */
class DotGit
{
    /**
     * Path to the .git file or directory
     *
     * @var string
     */
    private $dotGit = '';

    /**
     * Path to the real .git directory
     *
     * @var string
     */
    private $gitDir = '';

    /**
     * Is just an additional worktree
     *
     * @var bool
     */
    private $isAdditionalWorktree = false;

    /**
     * Constructor
     *
     * @param  string $pathToDotGit
     * @throws \RuntimeException
     */
    private function __construct(string $pathToDotGit)
    {
        // default repository with a .git directory
        if (is_dir($pathToDotGit)) {
            $this->gitDir = $pathToDotGit;
            return;
        }
        // additional worktree with a .git file referencing the original .git directory
        if (is_file($pathToDotGit)) {
            $dotGitContent = file_get_contents($pathToDotGit);
            $match         = [];
            preg_match('#^gitdir: (?<gitdir>.*\.git)#', $dotGitContent ?? '', $match);
            $dir = $match['gitdir'] ?? '';
            if (is_dir($dir)) {
                $this->gitDir               = $dir;
                $this->isAdditionalWorktree = true;
                return;
            }
        }
        throw new RuntimeException('invalid .git path');
    }

    /**
     * Returns the path to the .git file or directory
     *
     * @return string
     */
    public function path(): string
    {
        return $this->dotGit;
    }

    /**
     * Always returns the path .git repository directory
     *
     * @return string
     */
    public function gitDirectory(): string
    {
        return $this->gitDir;
    }

    /**
     * Returns true if the .git file indicated that we are in an additional worktree
     *
     * @return bool
     */
    public function isAdditionalWorktree(): bool
    {
        return $this->isAdditionalWorktree;
    }

    /**
     * Looks for a .git file or directory from a given path in all parent directories
     *
     * @param  string $path
     * @return \CaptainHook\Composer\DotGit
     * @throws \RuntimeException
     */
    public static function searchInPath(string $path): DotGit
    {
        while (file_exists($path)) {
            $dotGitPath = $path . '/.git';
            if (file_exists($dotGitPath)) {
                return new self($dotGitPath);
            }
            // if we checked the root directory already, break to prevent endless loop
            if ($path === dirname($path)) {
                break;
            }
            $path = dirname($path);
        }
        throw new RuntimeException('git directory not found');
    }
}
