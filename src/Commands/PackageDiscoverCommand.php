<?php

namespace Bfg\Installer\Commands;

use Bfg\Installer\Discover;
use Illuminate\Foundation\Console\PackageDiscoverCommand as IlluminatePackageDiscoverCommand;
use Illuminate\Foundation\PackageManifest;

/**
 * Class PackageDiscoverCommand
 * @package Bfg\Installer\Commands
 */
class PackageDiscoverCommand extends IlluminatePackageDiscoverCommand
{
    /**
     * The console command signature.
     *
     * @var string
     */
    protected $signature = 'package:discover';

    /**
     * Execute the console command.
     *
     * @param  \Illuminate\Foundation\PackageManifest  $manifest
     * @return void
     * @throws \Exception
     */
    public function handle(PackageManifest $manifest)
    {
        parent::handle($manifest);

        app(Discover::class)->handle($this);
    }
}
