<?php

namespace Bfg\Installer\Commands;

use Bfg\Installer\Processor\InstallProcessor;
use Bfg\Installer\Processor\UnInstallProcessor;
use Bfg\Installer\Providers\InstalledProvider;
use Illuminate\Console\Command;

/**
 * Class UnInstallCommand
 * @package Bfg\Installer\Commands
 */
class UnInstallCommand extends ProcessCommand
{
    public $is_uninstall = true;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'uninstall {package? : The package name}
                                {--f|force : Force uninstall}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run package uninstall';

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

            $package = \Installer::getPackageByName($name);

            if (
                \Installer::isInstalledPackage($package['provider']) ||
                \Installer::isPausedPackage($package['provider']) ||
                $this->option('force')
            ) {

                /** @var InstalledProvider $provider */
                $provider = app($package['provider']);

                if (
                    $this->option('force') &&
                    $provider instanceof InstalledProvider
                ) {
                    try {
                        $provider->uninstall(
                            app(UnInstallProcessor::class, [
                                'command' => $this,
                                'extension' => $package
                            ])
                        );

                        foreach ($package['extensions'] as $extension) {

                            $name_p = \Installer::getPackage($extension, 'name');

                            if ($name_p) {

                                $this->call(static::class, [
                                    'package' => $name_p,
                                    '--force' => !!$this->option('force')
                                ]);
                            }
                        }
                    } catch (\Throwable $throwable) {
                        $this->error("PHP Exception [{$throwable->getMessage()}]: {$throwable->getCode()}");
                        \Log::error($throwable);
                        return 1;
                    }

                    \Installer::set($package['provider'], 'install_complete', false)
                        ->dump();
                }

                \Installer::set($package['provider'], 'installed', false)
                    ->dump();

                $this->info("The package [{$name}] successfully uninstalled!");

            } else {
                $this->error("The package [{$name}] already uninstalled!");
            }

        } else {

            $this->error("Package [{$name}] is not found!");
        }

        return 0;
    }
}
