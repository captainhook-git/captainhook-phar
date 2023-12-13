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

use PharIo\ComposerDistributor\ConfiguredMediator;

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
     * Return the path to the captainhook distributor configuration file
     *
     * @return string
     */
    protected function getDistributorConfig(): string
    {
        return __DIR__ . '/../distributor.xml';
    }
}
