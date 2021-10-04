<?php

namespace Bfg\Installer\Commands;

use Illuminate\Console\Command;

/**
 * Class ProcessCommand.
 * @package Bfg\Installer\Commands
 */
abstract class ProcessCommand extends Command
{
    public $is_install = false;
    public $is_reinstall = false;
    public $is_uninstall = false;
    public $is_update = false;
}
