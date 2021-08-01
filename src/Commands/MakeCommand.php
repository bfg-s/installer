<?php

namespace Bfg\Installer\Commands;

use Composer\Json\JsonFormatter;
use Illuminate\Console\Command;

/**
 * Class MakeCommand
 * @package Bfg\Installer\Commands
 */
class MakeCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make {name : The making package name}
                                {--d|description= : The description of extension}
                                {--t|type=bfg-app : The type of extension}
                                {--namespace= : The namespace of extension}
                                {--ver=0.0.1 : The version of extension}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create package extension';

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
        $namespace = $this->argument('name');

        if (!preg_match('/[A-Za-z0-9_\-]{2,}\/[A-Za-z0-9_\-]{2,}/', $namespace)) {
            $this->error("The incorrect name must be the following pattern: user/package");

            return 1;
        }

        [$path, $name] = explode("/", $namespace);

        if (is_dir(base_path("vendor/{$namespace}"))) {
            $this->error("The package [{$namespace}] is already exists!");
        }

        $base_dir = base_path("{$path}/{$name}");

        foreach (
            [
                $base_dir.'/config',
                $base_dir.'/src',
            ] as $dir
        ) {
            if (!is_dir($dir)) {
                mkdir($dir, 0777, 1);
                $this->info("Created dir [".str_replace(base_path(), '', realpath($dir))."]!");
            }
        }

        foreach (
            [
                //$base_dir.'/public/.gitkeep' => '',
                $base_dir.'/.gitignore' => $this->get_stub('gitignore'),
                $base_dir.'/composer.json' => $this->get_stub('composer'),
                $base_dir.'/README.md' => $this->get_stub('README'),
                $base_dir.'/CHANGELOG.md' => $this->get_stub('CHANGELOG'),
                $base_dir.'/LICENSE.md' => $this->get_stub('LICENSE'),
                $base_dir.'/src/helpers.php' => $this->get_stub('helpers'),
                $base_dir.'/src/ServiceProvider.php' => $this->get_stub('ServiceProvider'),
            ] as $file => $file_data
        ) {
            if (!is_file($file)) {
                file_put_contents($file, $file_data);
                $this->info("Created file [".str_replace(base_path(), '', realpath($file))."]!");
            }
        }

        $this->add_repo_to_composer(str_replace(base_path().'/', '', $base_dir) . '/');

        $this->line("");
        $this->info("  For continue you can run: <comment>composer require {$path}/{$name}</comment>");

        return 0;
    }

    /**
     * @param  string  $file
     * @return false|string
     */
    protected function get_stub(string $file): bool|string
    {
        $data = file_get_contents(__DIR__ . "/Stubs/{$file}.stub");

        $name = $this->argument('name');

        list($folder, $extension) = explode("/", $name);

        $namespace_option = $this->option('namespace');

        if ($namespace_option) {

            $namespace_option = str_replace("/", "\\", $namespace_option);

            $namespace_option = explode("\\", $namespace_option);
        }

        $namespace = $this->makeNamespace($namespace_option ?: [$folder, $extension]);

        return str_replace([
            '{NAME}', '{DESCRIPTION}', '{FOLDER}', '{EXTENSION}', '{VERSION}',
            '{COMPOSER_NAMESPACE}', '{NAMESPACE}', '{SLUG}', '{TYPE}', '{DATE}'
        ], [
            $name,
            $this->option('description'),
            $folder,
            $extension,
            $this->option('version'),
            str_replace('\\', '\\\\', $namespace),
            $namespace,
            \Str::slug(str_replace("/", "_", $name), '_'),
            $this->option('type'),
            now()->format('Y-m-d')
        ], $data);
    }

    /**
     * @param  array  $parts
     * @return string
     */
    protected function makeNamespace(array $parts): string
    {
        return implode("\\", array_map('ucfirst', array_map('Str::camel', $parts)));
    }

    /**
     * @param  string  $path
     * @return bool
     */
    protected function add_repo_to_composer(string $path): bool
    {
        $base_composer = json_decode(file_get_contents(base_path('composer.json')), 1);

        if (!isset($base_composer['repositories'])) {
            $base_composer['repositories'] = [];
        }

        $path = trim($path, "/");

        if (!collect($base_composer['repositories'])->where('url', $path)->first()) {
            $base_composer['repositories'][] = ['type' => 'path', 'url' => $path];
            file_put_contents(base_path('composer.json'), JsonFormatter::format(json_encode($base_composer), false, true));
            $this->info("Added [{$path}] repository to composer.json!");
            return true;
        }

        return false;
    }
}
