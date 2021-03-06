<?php

namespace Bfg\Installer\Processor;

use Bfg\Installer\Commands\ProcessCommand;
use Illuminate\Console\Command;
use Illuminate\Database\Migrations\Migration;
use League\Flysystem\Adapter\Local as LocalAdapter;
use League\Flysystem\Filesystem as Flysystem;
use League\Flysystem\MountManager;
use Symfony\Component\Finder\Finder;

/**
 * Class Processor.
 * @package Bfg\Installer\Processor
 */
abstract class Processor
{
    /**
     * Processor constructor.
     * @param  Command  $command
     * @param  array  $extension
     */
    public function __construct(
        public Command $command,
        public array $extension
    ) {
    }

    /**
     * Publish and apply migrations.
     * @param  string  $path
     * @param  bool  $publish
     * @return bool
     */
    public function migrate(string $path, bool $publish = true): bool
    {
        if ($publish) {
            $this->publish($path, database_path('migrations'));
        }

        if (is_dir($path)) {
            $files = iterator_to_array(Finder::create()
                ->files()
                ->ignoreDotFiles(false)
                ->in($path)
                ->depth(0)
                ->sortByName(), false);

            if (! count($files)) {
                $this->command->info('Nothing to migrate.');

                return false;
            }

            foreach ($files as $file) {
                $migration_name = str_replace('.php', '', $file->getFilename());

                if (\DB::table('migrations')->where('migration', $migration_name)->first()) {
                    continue;
                }

                $class = class_in_file($file->getPathname());

                if (! class_exists($class) && is_file(database_path('migrations/'.$file->getFilename()))) {
                    include database_path('migrations/'.$file->getFilename());
                }

                if (! class_exists($class)) {
                    include $file->getPathname();
                }

                if (! class_exists($class)) {
                    $this->command->line("<comment>Non-migration:</comment> {$migration_name}");
                    continue;
                }

                $migration = new $class;

                if ($migration instanceof Migration) {
                    if (method_exists($migration, 'up')) {
                        $this->command->line("<comment>Migrating:</comment> {$migration_name}");
                        $startTime = microtime(true);
                        $migration->up();
                        \DB::table('migrations')->insert(['migration' => $migration_name, 'batch' => 1]);
                        $runTime = round(microtime(true) - $startTime, 2);
                        $this->command->line("<info>Migrated:</info>  {$migration_name} ({$runTime} seconds)");
                    } else {
                        $this->command->line("<comment>Non-migration:</comment> {$migration_name}");
                    }
                }
            }
        } else {
            $this->command->error("[{$path}] Is not directory");
            exit;
        }

        return true;
    }

    /**
     * Run migrate rollback.
     * @param  string  $path
     * @param  bool  $drop_publish
     * @return bool
     */
    public function migrateRollback(string $path, bool $drop_publish = true): bool
    {
        if (is_dir($path)) {
            $files = \File::files($path);

            if (! count($files)) {
                $this->command->info('Nothing to rollback.');

                return false;
            }

            foreach (array_reverse($files) as $file) {
                $class = class_in_file($file->getPathname());

                if (! class_exists($class) && is_file(database_path('migrations/'.$file->getFilename()))) {
                    include database_path('migrations/'.$file->getFilename());
                }

                if (! class_exists($class)) {
                    include $file->getPathname();
                }

                $migration_name = str_replace('.php', '', $file->getFilename());

                if (! class_exists($class)) {
                    $this->command->line("<comment>Non-migration:</comment> {$migration_name}");
                    continue;
                }

                $migration = new $class;

                if ($migration instanceof Migration) {
                    if (method_exists($migration, 'ignore') && ($migration->ignore() && ! $this->command->option('force'))) {
                        $this->command->line("<comment>Ignored-migration:</comment> {$migration_name}");
                        continue;
                    }
                    if (method_exists($migration, 'down')) {
                        $this->command->line("<comment>Rolling back:</comment> {$migration_name}");
                        $startTime = microtime(true);
                        $migration->down();
                        \DB::table('migrations')->where('migration', $migration_name)->delete();
                        $runTime = round(microtime(true) - $startTime, 2);
                        $this->command->line("<info>Rolled back:</info>  {$migration_name} ({$runTime} seconds)");
                    } else {
                        $this->command->line("<comment>Non-migration:</comment> {$migration_name}");
                    }
                }
            }

            if ($drop_publish) {
                $this->unpublish($path, database_path('migrations'));
            }
        } else {
            $this->command->error("[{$path}] Is not directory");
            exit;
        }

        return true;
    }

    /**
     * Publish extension assets.
     * @param  string|array  $from
     * @param  string|null  $to
     * @param  bool  $force
     * @return bool
     */
    public function publish($from, string $to = null, bool $force = false): bool
    {
        if (is_array($from)) {
            foreach ($from as $from_arr => $to_arr) {
                $this->publish($from_arr, $to_arr, $force);
            }

            return true;
        }

        $status = function ($type) use ($from, $to) {
            $from = str_replace(base_path(), '', realpath($from));

            $to = str_replace(base_path(), '', realpath($to));

            $this->command->line('<info>Copied '.$type.'</info> <comment>['.$from.']</comment> <info>To</info> <comment>['.$to.']</comment>');
        };

        if (is_file($from)) {
            $directory = dirname($to);

            if (! is_dir($directory)) {
                \File::makeDirectory($directory, 0755, true);
            }

            \File::copy($from, $to);

            $status('File');
        } elseif (is_dir($from)) {
            $manager = new MountManager([
                'from' => new Flysystem(new LocalAdapter($from)),
                'to' => new Flysystem(new LocalAdapter($to)),
            ]);

            foreach ($manager->listContents('from://', true) as $file) {
                if ($file['type'] === 'file' && (! $manager->has('to://'.$file['path']) || $force)) {
                    $manager->put('to://'.$file['path'], $manager->read('from://'.$file['path']));
                }
            }

            $status('Directory');
        } else {
            return false;
        }

        return true;
    }

    /**
     * Remove published assets.
     * @param  string|array  $where
     * @param  string|bool  $in
     * @return int
     */
    public function unpublish($where, $in = null): int
    {
        $deleted = 0;

        if (is_array($where)) {
            foreach ($where as $where_arr => $in_arr) {
                $deleted += $this->unpublish($where_arr, $in_arr);
            }

            return $deleted;
        }

        $where_real_path = str_replace(base_path(), '', realpath($where));

        $in_real_path = str_replace(base_path(), '', realpath($in));

        if (is_file($where) && is_file($in)) {
            if (basename($where) === basename($in)) {
                try {
                    unlink($in);
                    $deleted++;
                } catch (\Exception $e) {
                }

                if ($deleted) {
                    $this->command->line("<info>Removed file</info> <comment>[{$in_real_path}]</comment> <info>how</info> <comment>[{$where_real_path}]</comment>");
                }
            }
        } elseif (is_dir($where) && is_dir($in)) {
            $in_files = collect(\File::allFiles($in, true))->map(function (\Symfony\Component\Finder\SplFileInfo $info) {
                return ['relativePath' => $info->getRelativePathname(), 'pathname' => $info->getPathname()];
            });

            $where_files = collect(\File::allFiles($where, true))->map(function (\Symfony\Component\Finder\SplFileInfo $info) {
                return ['relativePath' => $info->getRelativePathname(), 'pathname' => $info->getPathname()];
            });

            foreach ($where_files as $where_file) {
                if ($in_file = $in_files->where('relativePath', $where_file['relativePath'])->first()) {
                    try {
                        unlink($in_file['pathname']);
                        $deleted++;
                    } catch (\Exception $e) {
                    }
                }
            }

            $this->command->line("<info>The cleaned directory</info> <comment>[{$in_real_path}]</comment> <info>from <comment>[{$deleted}]</comment> files of the directory</info> <comment>[{$where_real_path}]</comment>");
        }

        return $deleted;
    }
}
