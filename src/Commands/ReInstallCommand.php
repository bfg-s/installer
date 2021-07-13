<?php

namespace Bfg\Installer\Commands;

use Bfg\Installer\Processor\InstallProcessor;
use Bfg\Installer\Processor\UnInstallProcessor;
use Bfg\Installer\Providers\InstalledProvider;
use Illuminate\Console\Command;

/**
 * Class ReInstallCommand
 * @package Bfg\Installer\Commands
 */
class ReInstallCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reinstall {package? : The package name}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run package reinstall';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $name = $this->argument('package') ?? 'app';

        if (!\Installer::isHasPackageByName($name)) {

            if ($index = \Installer::collect()->where('index', $name)->first()) {

                $name = $index['name'];
            }
        }

        if (\Installer::isHasPackageByName($name)) {

            $this->call('uninstall', ['package' => $name, '--force' => true]);
            $this->call('install', ['package' => $name]);

        } else {

            $this->error("Package [{$name}] is not found!");
        }

        return 0;
    }
}
