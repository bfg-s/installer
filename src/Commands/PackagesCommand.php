<?php

namespace Bfg\Installer\Commands;

use Illuminate\Console\Command;

/**
 * Class PackagesCommand
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
        {--p|path : Show path provider of extension}';

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
            "Name", "Description", "Version", "Child", "Type", "Installed", "Works"
        ];

        if ($this->option('path')) {

            $headers[] = "Provider path";
        }

        $this->table($headers, collect(\Installer::packages())->map(function ($i, $class) {

            $result = [
                $i['name'],
                $i['description'],
                "<info>{$i['version']}</info>",
                "<comment>{$i['child']}</comment>",
                "<comment>{$i['type']}</comment>",
                $i['install_complete'] ? "<info>Yes</info>":"<comment>No</comment>",
                $this->installedInformation($class, $i),
            ];

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
            return "<info>Yes</info>";
        } else {
            if ($extension['installed']) {
                return "Paused";
            } else {
                return "<comment>No</comment>";
            }
        }
    }
}
