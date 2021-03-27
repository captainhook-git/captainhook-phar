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

namespace CaptainHook\Composer\Plugin;

use Composer\Plugin\Capability\CommandProvider as CapabilityCommandProvider;
use CaptainHook\Composer\Command\Configure;
use CaptainHook\Composer\Command\Install;

/**
 * Command Provider
 *
 * @package CaptainHook
 * @author  Sebastian Feldmann <sf@sebastian-feldmann.info>
 * @link    https://github.com/captainhookphp/captainhook
 * @since   Class available since Release 6.0.0
 */
class CommandProvider implements CapabilityCommandProvider
{
    public function getCommands(): array
    {
        return [
            new Configure(),
            new Install(),
        ];
    }
}
