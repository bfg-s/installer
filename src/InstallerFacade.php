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
     *  'composer_name' => string|null,
     *  'composer_version' => string|null,
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
                'composer_name' => null,
                'composer_version' => null,
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

            $this->buildGeneralData($providerPackageClass, false);
        }

        return $this;
    }

    /**
     * @param  string  $provider_name
     * @param  bool  $force
     * @return $this
     * @throws \ReflectionException
     */
    public function buildGeneralData(string $provider_name, bool $force = true): static
    {
        if (!$this->packages[$provider_name]['path'] || $force) {
            $ref = new \ReflectionClass($provider_name);
            $this->packages[$provider_name]['path'] =
                trim(str_replace(base_path(), '', $ref->getFileName()), '/\\');
        }

        if (
            $this->packages[$provider_name]['path'] &&
            (!$this->packages[$provider_name]['dir'] || $force)
        ) {
            $this->packages[$provider_name]['dir'] =
                trim(str_replace(basename($this->packages[$provider_name]['path']), "",
                    str_replace(base_path(), '', $this->packages[$provider_name]['path'])), '/\\');
        }

        if (
            $this->packages[$provider_name]['dir'] &&
            $this->packages[$provider_name]['path'] &&
            (!$this->packages[$provider_name]['composer_file'] || $force)
        ) {
            $funded = [];

            foreach (explode(DIRECTORY_SEPARATOR, $this->packages[$provider_name]['dir']) as $segment) {

                if ($segment == 'app') {

                    $fp = "composer.json";
                } else {

                    $funded[] = $segment;

                    $p = implode(DIRECTORY_SEPARATOR, $funded);

                    $fp = $p . DIRECTORY_SEPARATOR . "composer.json";
                }

                if (is_file(base_path($fp))) {

                    $this->packages[$provider_name]['composer_file'] = $fp;

                    break;
                }
            }
        }

        if (
            $this->packages[$provider_name]['dir'] &&
            $this->packages[$provider_name]['path'] &&
            $this->packages[$provider_name]['composer_file'] &&
            (!$this->packages[$provider_name]['composer_name'] || $force)
        ) {
            $data = json_decode(file_get_contents($this->packages[$provider_name]['composer_file']), 1);

            $this->packages[$provider_name]['composer_name'] = $data['name'];
        }

        if (
            $this->packages[$provider_name]['dir'] &&
            $this->packages[$provider_name]['path'] &&
            $this->packages[$provider_name]['composer_file'] &&
            $this->packages[$provider_name]['composer_name'] &&
            (!$this->packages[$provider_name]['composer_version'] || $force) &&
            is_file(base_path('composer.lock'))
        ) {
            $data = json_decode(file_get_contents(base_path('composer.lock')), 1);

            if ($data && isset($data['packages'])) {

                $data = collect($data['packages'])->where('name', $this->packages[$provider_name]['composer_name'])->first();

                if ($data && isset($data['version'])) {

                    $this->packages[$provider_name]['composer_version'] = $data['version'];
                }
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
        $test = $this->isHasPackage($provider_name) &&
            $this->packages[$provider_name]['installed'] &&
            $this->packages[$provider_name]['install_complete'];

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
