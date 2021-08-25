<?php

namespace Bfg\Installer\Commands;

use Bfg\Installer\Processor\DumpAutoloadProcessor;
use Bfg\Installer\Processor\UnInstallProcessor;
use Bfg\Installer\Processor\UpdateProcessor;
use Bfg\Installer\Providers\InstalledProvider;
use Illuminate\Console\Command;

/**
 * Class DumpCommand
 * @package Bfg\Installer\Commands
 */
class DumpCommand extends ProcessCommand
{
    public $is_update = true;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dump-autoload {package? : The package name}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run package update';

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
     * @throws \ReflectionException
     */
    public function handle()
    {
        $name = $this->argument('package') ?? 'bfg/installer';

        if (!\Installer::isHasPackageByName($name)) {

            if ($index = \Installer::collect()->where('index', $name)->first()) {

                $name = $index['name'];
            }
        }

        if (\Installer::isHasPackageByName($name)) {

            $package = \Installer::getPackageByName($name);

            /** @var InstalledProvider $provider */
            $provider = app($package['provider']);

            if (
                $provider instanceof InstalledProvider
            ) {
                try {
                    $provider->dump(
                        app(DumpAutoloadProcessor::class, [
                            'command' => $this,
                            'extension' => $package
                        ])
                    );
                    foreach ($package['extensions'] as $extension) {

                        $name_p = \Installer::getPackage($extension, 'name');

                        if ($name_p) {

                            $this->call(static::class, [
                                'package' => $name_p
                            ]);
                        }
                    }
                } catch (\Throwable $throwable) {
                    $this->error("PHP Exception [{$throwable->getMessage()}]: {$throwable->getCode()}");
                    \Log::error($throwable);
                    return 1;
                }

                \Installer::buildGeneralData($package['provider'], true);
            }

            $this->info("The package [{$name}] successfully dumped!");

        } else {

            $this->error("Package [{$name}] is not found!");
        }

        return 0;
    }
}
