<?php

namespace Bfg\Installer\Commands;

use Illuminate\Console\Command;

/**
 * Class PackagesCommand.
 * @package Bfg\Installer\Commands
 */
class PackagesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'packages
        {--p|path : Show path provider of extensions}
        {--c|child : Show child name of extensions}
        {--t|type : Show type of extensions}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Show all packages';

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
        $headers = [
            'Index', 'Name', 'Description', 'Version', 'Installed', 'Works',
        ];

        if ($this->option('child')) {
            $headers[] = 'Child';
        }

        if ($this->option('type')) {
            $headers[] = 'Type';
        }

        if ($this->option('path')) {
            $headers[] = 'Provider path';
        }

        $this->table($headers, collect(\Installer::packages())->map(function ($i, $class) {
            $result = [
                $i['index'],
                $i['name'],
                $i['description'],
                '<info>'.($i['version'] ?: $i['composer_version']).'</info>',
                $i['install_complete'] ? '<info>Yes</info>' : '<comment>No</comment>',
                $this->installedInformation($class, $i),
            ];

            if ($this->option('child')) {
                $result[] = $i['child'];
            }

            if ($this->option('type')) {
                $result[] = $i['type'];
            }

            if ($this->option('path')) {
                $result[] = $i['path'];
            }

            return $result;
        })->toArray());

        return 0;
    }

    /**
     * @param  string  $class
     * @param  array  $extension
     * @return string
     */
    protected function installedInformation(string $class, array $extension): string
    {
        if (\Installer::isInstalledPackage($class)) {
            return '<info>Yes</info>';
        } else {
            if ($extension['installed']) {
                return 'Paused';
            } else {
                return '<comment>No</comment>';
            }
        }
    }
}
