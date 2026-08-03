<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Process\Factory as ProcessFactory;
use Illuminate\Support\Composer;
use Illuminate\Support\Str;
use InterNACHI\Modular\Support\ModuleRegistry;
use JsonException;

#[Signature('starter:make-module
    {name : The name of the module}
    {--accept-default-namespace : Skip the default namespace confirmation}')]
#[Description('Create, install, and synchronize a module')]
class MakeModuleCommand extends Command
{
    public function __construct(
        private readonly Kernel $artisan,
        private readonly Composer $composer,
        private readonly Filesystem $filesystem,
        private readonly ModuleRegistry $modules,
        private readonly ProcessFactory $processes,
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $moduleName = Str::kebab((string) $this->argument('name'));
        $makeArguments = ['name' => $moduleName];

        if ($this->option('accept-default-namespace')) {
            $makeArguments['--accept-default-namespace'] = true;
        }

        if ($this->artisan->call('make:module', $makeArguments, $this->output) !== self::SUCCESS) {
            return self::FAILURE;
        }

        $moduleComposerPath = $this->modules->getModulesPath().DIRECTORY_SEPARATOR.$moduleName.DIRECTORY_SEPARATOR.'composer.json';
        $modulePackage = $this->modulePackageName($moduleComposerPath);

        if ($modulePackage === null) {
            $this->components->error("The module [{$moduleName}] was not created with a valid composer.json file.");

            return self::FAILURE;
        }

        $this->components->info("Installing module package [{$modulePackage}]");

        $composerCommand = [
            ...$this->composer->setWorkingPath($this->laravel->basePath())->findComposer(),
            'update',
            $modulePackage,
        ];

        if (! $this->input->isInteractive()) {
            $composerCommand[] = '--no-interaction';
        }

        $composerResult = $this->processes
            ->path($this->laravel->basePath())
            ->forever()
            ->env(['COMPOSER_MEMORY_LIMIT' => '-1'])
            ->run($composerCommand, fn (string $type, string $output) => $this->output->write($output));

        if ($composerResult->failed()) {
            $this->components->error("Composer could not install module package [{$modulePackage}].");

            return self::FAILURE;
        }

        if ($this->artisan->call('modules:sync', [], $this->output) !== self::SUCCESS) {
            $this->components->error("Module [{$moduleName}] was installed, but its configuration could not be synchronized.");

            return self::FAILURE;
        }

        $this->components->info("Module [{$moduleName}] is ready.");

        return self::SUCCESS;
    }

    private function modulePackageName(string $composerPath): ?string
    {
        if (! $this->filesystem->isFile($composerPath)) {
            return null;
        }

        try {
            $composer = json_decode($this->filesystem->get($composerPath), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        $packageName = $composer['name'] ?? null;

        return is_string($packageName) && $packageName !== '' ? $packageName : null;
    }
}
