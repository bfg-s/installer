<?php

namespace Bfg\Installer;

use Bfg\Dev\Commands\DumpAutoload;
use Bfg\Installer\Commands\InstallCommand;
use Bfg\Installer\Commands\MakeCommand;
use Bfg\Installer\Commands\PackageDiscoverCommand;
use Bfg\Installer\Commands\PackagesCommand;
use Bfg\Installer\Commands\ReInstallCommand;
use Bfg\Installer\Commands\UnInstallCommand;
use Bfg\Installer\Commands\UpdateCommand;
use Bfg\Installer\Providers\InstalledProvider;

/**
 * Class ServiceProvider
 * @package Bfg\Doc
 */
class ServiceProvider extends InstalledProvider
{
    /**
     * The name of extension.
     * @var string|null
     */
    public ?string $name = 'app';

    /**
     * The child type for sub
     * extensions of extension.
     * @var string|null
     */
    public ?string $child = 'bfg-app';

    /**
     * The type to determine who
     * owns the extension.
     * @var string|null
     */
    public ?string $type = null;

    /**
     * The description of extension.
     * @var string|null
     */
    public ?string $description = 'The main application';

    /**
     * Set as installed by default.
     * @var bool
     */
    public bool $installed = true;

    /**
     * Executed when the provider is registered
     * and the extension is installed.
     * @return void
     */
    function installed(): void
    {
        /**
         * Register shutdown
         */
        $this->registerShutDown();

        /**
         * Extend default laravel discover command
         */
        $this->app->extend('command.package.discover', function () {
            return new PackageDiscoverCommand();
        });
    }

    /**
     * Bootstrap services.
     * @return void
     */
    public function boot()
    {
        /**
         * Register package commands
         */
        $this->commands([
            InstallCommand::class,
            UnInstallCommand::class,
            UpdateCommand::class,
            PackagesCommand::class,
            ReInstallCommand::class,
            MakeCommand::class,
        ]);

        parent::boot();
    }

    /**
     * Register shutdown function for development dump
     */
    protected function registerShutDown()
    {
        if (\App::isLocal()) {

            register_shutdown_function(function () {

                \Installer::dump();
            });
        }
    }

    /**
     * Executed when the provider run method
     * "boot" and the extension is installed.
     * @return void
     */
    function run(): void
    {
        //
    }
}
