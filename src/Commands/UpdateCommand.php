<?php

namespace Bfg\Installer\Commands;

use Bfg\Installer\Processor\UnInstallProcessor;
use Bfg\Installer\Processor\UpdateProcessor;
use Bfg\Installer\Providers\InstalledProvider;
use Illuminate\Console\Command;

/**
 * Class UpdateCommand
 * @package Bfg\Installer\Commands
 */
class UpdateCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'update {package? : The package name}
                            {--r|reinstall : Reinstall the package after update}';

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
        $name = $this->argument('package') ?? 'app';

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
                    $provider->update(
                        app(UpdateProcessor::class, [
                            'command' => $this,
                            'extension' => $package
                        ])
                    );
                } catch (\Throwable $throwable) {
                    $this->error("PHP Exception [{$throwable->getMessage()}]: {$throwable->getCode()}");
                    \Log::error($throwable);
                    return 1;
                }

                \Installer::buildGeneralData($package['provider'], true);
            }

            $this->info("The package [{$name}] successfully updated!");

            if ($this->option('reinstall')) {

                $this->call('reinstall', [
                    'package' => $name
                ]);
            }

        } else {

            $this->error("Package [{$name}] is not found!");
        }

        return 0;
    }
}
