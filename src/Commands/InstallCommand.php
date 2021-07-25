<?php

namespace Bfg\Installer\Commands;

use Bfg\Installer\Processor\InstallProcessor;
use Bfg\Installer\Providers\InstalledProvider;
use Illuminate\Console\Command;

/**
 * Class InstallCommand
 * @package Bfg\Installer\Commands
 */
class InstallCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'install {package? : The package name or index}
                            {--u|update : Update the package after install}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run package install';

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
                !\Installer::isInstalledPackage($package['provider']) &&
                !\Installer::isPausedPackage($package['provider'])
            ) {

                \Installer::set($package['provider'], 'installed', true)
                    ->dump();

                /** @var InstalledProvider $provider */
                $provider = app($package['provider']);

                if (
                    !$package['install_complete'] &&
                    $provider instanceof InstalledProvider
                ) {
                    try {
                        $provider->install(
                            app(InstallProcessor::class, [
                                'command' => $this,
                                'extension' => $package
                            ])
                        );

                        foreach ($package['extensions'] as $extension) {

                            $name = \Installer::getPackage($extension, 'name');

                            if ($name) {

                                $this->call(static::class, [
                                    'package' => $name,
                                    '--update' => !!$this->option('update')
                                ]);
                            }
                        }
                    } catch (\Throwable $throwable) {
                        $this->error("PHP Exception [{$throwable->getMessage()}]: {$throwable->getCode()}");
                        \Log::error($throwable);
                        return 1;
                    }
                }

                \Installer::set($package['provider'], 'install_complete', true)
                    ->dump();

                $this->info("The package [{$name}] successfully installed!");

                if ($this->option('update')) {

                    $this->call('update', [
                        'package' => $name
                    ]);
                }

            } else {
                $this->error("The package [{$name}] already installed!");
            }

        } else {

            $this->error("Package [{$name}] is not found!");
        }

        return 0;
    }
}
