<?php

namespace Bfg\Installer;

/**
 * Class InstallerFacade
 * @package Bfg\Installer
 */
class InstallerFacade
{
    /**
     * All list of packages
     * 'ProviderPackageClass' => [
     *  'installed' => bool,
     *  'install_complete' => bool,
     *  'name' => string,
     *  'logo' => string|null,
     *  'version' => string,
     *  'type' => string|null,
     *  'description' => string|null,
     *  'child' => string|null,
     *  'parent' => string|null,
     *  'path' => string|null,
     *  'dir' => string|null,
     *  'composer_file' => string|null,
     *  'extensions' => array,
     * ]
     *
     * @var array|null
     */
    protected ?array $packages = null;

    /**
     * InstallerFacade constructor.
     */
    public function __construct()
    {
        if ($this->packages === null) {
            $file = storage_path("packages.php");
            if (is_file($file)) {
                $this->packages = include $file;
            } else {
                $this->packages = [];
            }
        }
    }

    /**
     * Get all packages
     * @return array|null
     */
    public function packages(): ?array
    {
        return $this->packages;
    }

    /**
     * @param  string  $providerPackageClass
     * @param  array  $settings
     * @return $this
     * @throws \ReflectionException
     */
    public function registrationPackage(string $providerPackageClass, array $settings = []): static
    {
        if (!$this->isHasPackage($providerPackageClass)) {
            $this->packages[$providerPackageClass] = array_merge([
                'installed' => false,
                'install_complete' => false,
                'name' => $providerPackageClass,
                'logo' => null,
                'provider' => $providerPackageClass,
                'version' => '0.0.1',
                'child' => null,
                'type' => null,
                'parent' => null,
                'path' => null,
                'dir' => null,
                'composer_file' => null,
                'description' => null,
                'extensions' => [],
            ], $settings);
        }

        if (\App::isLocal() || !is_file(config('installer.map_cache'))) {
            if ($this->isHasPackage($providerPackageClass)) {
                $this->packages[$providerPackageClass] = array_merge(
                    $this->packages[$providerPackageClass],
                    $settings
                );
            }

            $this->updatePackagesExtensions();

            if (!$this->packages[$providerPackageClass]['path']) {
                $ref = new \ReflectionClass($providerPackageClass);
                $this->packages[$providerPackageClass]['path'] =
                    trim(str_replace(base_path(), '', $ref->getFileName()), '/\\');
            }

            if (
                !$this->packages[$providerPackageClass]['dir'] &&
                $this->packages[$providerPackageClass]['path']
            ) {
                $this->packages[$providerPackageClass]['dir'] =
                    trim(str_replace(basename($this->packages[$providerPackageClass]['path']), "",
                        str_replace(base_path(), '', $this->packages[$providerPackageClass]['path'])), '/\\');
            }

            if (
                $this->packages[$providerPackageClass]['dir'] &&
                $this->packages[$providerPackageClass]['path']
            ) {

            }
        }

        return $this;
    }

    /**
     * Set package property data
     *
     * @param  string  $provider_name
     * @param  string  $parameter
     * @param  mixed|null  $value
     * @return $this
     */
    public function set(string $provider_name, string $parameter, mixed $value = null): static
    {
        if ($this->isHasPackage($provider_name)) {
            $this->packages[$provider_name][$parameter] = $value;
        }

        return $this;
    }

    /**
     * @param  string  $provider_name
     * @return bool
     */
    public function isInstalledPackage(string $provider_name): bool
    {
        $test = $this->isHasPackage($provider_name) && $this->packages[$provider_name]['installed'];

        if ($test && $this->packages[$provider_name]['parent']) {
            $test = $this->isInstalledPackage($this->packages[$provider_name]['parent']);
        }

        return $test;
    }

    /**
     * @param  string  $provider_name
     * @return bool
     */
    public function isPausedPackage(string $provider_name): bool
    {
        $test = $this->isHasPackage($provider_name) && $this->packages[$provider_name]['installed'];

        if ($test && !$this->isInstalledPackage($provider_name)) {
            return true;
        }

        return false;
    }

    /**
     * @param  string  $provider_name
     * @return bool
     */
    public function isHasPackage(string $provider_name): bool
    {
        return $this->packages && isset($this->packages[$provider_name]);
    }

    /**
     * @param  string  $name
     * @return bool
     */
    public function isHasPackageByName(string $name): bool
    {
        return !!$this->getPackageByName($name);
    }

    /**
     * @param  string  $name
     * @return array|null
     */
    public function getPackageByName(string $name): ?array
    {
        return collect($this->packages)->where('name', $name)->first();
    }

    /**
     * Get package or package data
     * @param  string  $provider_name
     * @param  string|null  $property_name
     * @param  mixed|null  $default
     * @return mixed
     */
    public function getPackage(string $provider_name, string $property_name = null, mixed $default = null): mixed
    {
        $result = $this->isHasPackage($provider_name) ? $this->packages[$provider_name] : $default;

        if (
            $result &&
            $property_name &&
            array_key_exists($property_name, $result)
        ) {
            return $result[$property_name] ?: $default;
        }

        return $result;
    }

    /**
     * Update package extensions
     * @param  string  $provider_name
     */
    public function updatePackageExtensions(string $provider_name)
    {
        if (
            isset($this->packages[$provider_name]['child']) &&
            $this->packages[$provider_name]['child']
        ) {
            foreach ($this->packages as $name => $package) {
                if (
                    $package['type'] &&
                    $package['type'] == $this->packages[$provider_name]['child']
                ) {
                    $this->packages[$name]['parent'] = $provider_name;
                    $this->packages[$provider_name]['extensions'][$name] = $name;
                }
            }
        }
    }

    /**
     * Update packages extensions
     */
    public function updatePackagesExtensions()
    {
        if ($this->packages) {
            foreach (array_keys($this->packages) as $provider_name) {
                $this->updatePackageExtensions($provider_name);
            }
        }
    }

    /**
     * Dump all packages data
     */
    public function dump(): bool
    {
        return !!file_put_contents(
            storage_path("packages.php"),
            array_entity($this->packages)->wrap('php', 'return')->render()
        );
    }
}
