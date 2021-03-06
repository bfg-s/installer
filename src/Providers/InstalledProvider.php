<?php

namespace Bfg\Installer\Providers;

use Bfg\Installer\Processor\DumpAutoloadProcessor;
use Bfg\Installer\Processor\InstallProcessor;
use Bfg\Installer\Processor\UnInstallProcessor;
use Bfg\Installer\Processor\UpdateProcessor;
use Illuminate\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use JetBrains\PhpStorm\Pure;

/**
 * Class InstalledProvider.
 * @package Bfg\Installer\Providers
 */
abstract class InstalledProvider extends ServiceProvider
{
    /**
     * The version of extension.
     * @var string
     */
    public ?string $version = null;

    /**
     * The name of extension.
     * @var string|null
     */
    public ?string $name = null;

    /**
     * The child type for sub
     * extensions of extension.
     * @var string|null
     */
    public ?string $child = null;

    /**
     * The type to determine who
     * owns the extension.
     * @var string|null
     */
    public ?string $type = 'bfg-app';

    /**
     * The logo of extension.
     * @var string|null
     */
    public ?string $logo = null;

    /**
     * Set as installed by default.
     * @var bool
     */
    public bool $installed = false;

    /**
     * InstalledProvider constructor.
     * @param mixed|\Illuminate\Contracts\Foundation\Application $app
     */
    public function __construct(Application $app)
    {
        parent::__construct($app);

        $app->bind(static::class, fn () => $this);
    }

    /**
     * Executed when the provider is registered
     * and the extension is installed.
     * @return void
     */
    abstract public function installed(): void;

    /**
     * Executed when the provider run method
     * "boot" and the extension is installed.
     * @return void
     */
    abstract public function run(): void;

    /**
     * Executed when the parent provider is
     * registered and the extension is installed.
     * @param  InstalledProvider  $provider
     * @return static
     */
    public function installed_parent(self $provider): static
    {
        return $this;
    }

    /**
     * Executed when the parent provider run method
     * "boot" and the extension is installed.
     * @param  InstalledProvider  $provider
     * @return static
     */
    public function run_parent(self $provider): static
    {
        return $this;
    }

    /**
     * Run on install extension.
     * @param  InstallProcessor  $processor
     */
    public function install(InstallProcessor $processor)
    {
        $processor->command->line('Installation of '.($this->name ? ucfirst($this->name) : static::class).'...');
    }

    /**
     * Run on update extension.
     * @param  UpdateProcessor  $processor
     */
    public function update(UpdateProcessor $processor)
    {
        $processor->command->line('Updating of '.($this->name ? ucfirst($this->name) : static::class).'...');
    }

    /**
     * Run on uninstall extension.
     * @param  UnInstallProcessor  $processor
     */
    public function uninstall(UnInstallProcessor $processor)
    {
        $processor->command->line('Uninstalling of '.($this->name ? ucfirst($this->name) : static::class).'...');
    }

    /**
     * Run on dump extension.
     * @param  DumpAutoloadProcessor  $processor
     */
    public function dump(DumpAutoloadProcessor $processor)
    {
        $processor->command->info('> Dumping of '.($this->name ? ucfirst($this->name) : static::class).'...');
    }

    /**
     * Get extension data.
     * @return array|null
     */
    public function extension(): ?array
    {
        return \Installer::getPackage(static::class);
    }

    /**
     * Register route settings.
     * @return void
     * @throws \ReflectionException
     */
    public function register()
    {
        \Installer::registrationPackage(static::class, [
            'installed' => $this->installed,
            'name' => $this->name ?? static::class,
            'version' => $this->version,
            'child' => $this->child,
            'type' => $this->type,
            'provider' => static::class,
        ]);

        if (\Installer::isInstalledPackage(static::class)) {
            $this->installed();

            foreach (\Installer::getPackage(static::class, 'extensions', []) as $item) {
                app($item)->installed_parent($this);
            }
        }
    }

    /**
     * Bootstrap services.
     * @return void
     */
    public function boot()
    {
        if (\Installer::isInstalledPackage(static::class)) {
            $this->run();

            foreach (\Installer::getPackage(static::class, 'extensions', []) as $item) {
                app($item)->run_parent($this);
            }
        }
    }

    /**
     * Boot extensions provider.
     * @param  string  $method
     * @param ...$params
     */
    public function provideExtensionMethod(string $method, ...$params)
    {
        $extension = $this->extension();

        foreach ($extension['extensions'] as $extension) {
            app($extension)->{$method}(...$params);
        }
    }

    public function __call(string $name, array $arguments)
    {
    }
}
