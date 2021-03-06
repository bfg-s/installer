<?php

namespace Bfg\Installer;

use Bfg\Installer\Processor\DumpAutoloadProcessor;
use Bfg\Installer\Providers\InstalledProvider;
use Illuminate\Console\Command;

/**
 * Class Discover.
 * @package Bfg\Installer
 */
class Discover
{
    /**
     * @param  Command  $command
     * @return void
     */
    public function handle(Command $command)
    {
        foreach (array_reverse(\Installer::packages()) as $package) {

            /** @var InstalledProvider $provider */
            $provider = app($package['provider']);

            if (
                $provider instanceof InstalledProvider &&
                \Installer::isInstalledPackage($package['provider'])
            ) {
                try {
                    $provider->dump(
                        app(DumpAutoloadProcessor::class, [
                            'command' => $command,
                            'extension' => $package,
                        ])
                    );
                } catch (\Throwable $throwable) {
                    $command->error("PHP Exception [{$throwable->getMessage()}]: {$throwable->getCode()}");
                    \Log::error($throwable);
                }
            }
        }

        \Installer::dump();
    }
}
