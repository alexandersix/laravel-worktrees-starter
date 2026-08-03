<?php

use App\Console\Commands\MakeModuleCommand;
use Illuminate\Console\Command;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Process\Factory as ProcessFactory;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Composer;
use InterNACHI\Modular\Support\ModuleRegistry;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    $this->artisan = Mockery::mock(Kernel::class);
    $this->composer = Mockery::mock(Composer::class);
    $this->filesystem = Mockery::mock(Filesystem::class);
    $this->modules = Mockery::mock(ModuleRegistry::class);
    $this->processes = (new ProcessFactory)->fake();
    $this->command = new MakeModuleCommand(
        $this->artisan,
        $this->composer,
        $this->filesystem,
        $this->modules,
        $this->processes,
    );
    $this->command->setLaravel($this->app);
});

test('creates installs and synchronizes a module', function () {
    $composerPath = '/tmp/app-modules/billing/composer.json';

    $this->artisan->shouldReceive('call')
        ->once()
        ->with('make:module', [
            'name' => 'billing',
            '--accept-default-namespace' => true,
        ], Mockery::type(OutputInterface::class))
        ->andReturn(Command::SUCCESS);
    $this->artisan->shouldReceive('call')
        ->once()
        ->with('modules:sync', [], Mockery::type(OutputInterface::class))
        ->andReturn(Command::SUCCESS);
    $this->modules->shouldReceive('getModulesPath')
        ->once()
        ->andReturn('/tmp/app-modules');
    $this->filesystem->shouldReceive('isFile')
        ->once()
        ->with($composerPath)
        ->andReturnTrue();
    $this->filesystem->shouldReceive('get')
        ->once()
        ->with($composerPath)
        ->andReturn('{"name":"acme/billing"}');
    $this->composer->shouldReceive('setWorkingPath')
        ->once()
        ->with(base_path())
        ->andReturnSelf();
    $this->composer->shouldReceive('findComposer')
        ->once()
        ->andReturn(['composer']);

    $tester = new CommandTester($this->command);
    $exitCode = $tester->execute([
        'name' => 'Billing',
        '--accept-default-namespace' => true,
    ], ['interactive' => false]);

    expect($exitCode)->toBe(Command::SUCCESS)
        ->and($tester->getDisplay())->toContain('Module [billing] is ready.');

    $this->processes->assertRan(fn (PendingProcess $process) => $process->command === [
        'composer',
        'update',
        'acme/billing',
        '--no-interaction',
    ] && $process->path === base_path()
        && $process->timeout === null
        && $process->environment === ['COMPOSER_MEMORY_LIMIT' => '-1']);
});

test('stops when module creation does not produce composer metadata', function () {
    $this->artisan->shouldReceive('call')
        ->once()
        ->with('make:module', ['name' => 'billing'], Mockery::type(OutputInterface::class))
        ->andReturn(Command::SUCCESS);
    $this->artisan->shouldNotReceive('call')->with('modules:sync', Mockery::any(), Mockery::any());
    $this->modules->shouldReceive('getModulesPath')
        ->once()
        ->andReturn('/tmp/app-modules');
    $this->filesystem->shouldReceive('isFile')
        ->once()
        ->with('/tmp/app-modules/billing/composer.json')
        ->andReturnFalse();

    $tester = new CommandTester($this->command);
    $exitCode = $tester->execute(['name' => 'Billing']);

    expect($exitCode)->toBe(Command::FAILURE)
        ->and($tester->getDisplay())->toContain('was not created with a valid composer.json file');

    $this->processes->assertNothingRan();
});

test('stops before synchronization when composer fails', function () {
    $composerPath = '/tmp/app-modules/billing/composer.json';

    $this->artisan->shouldReceive('call')
        ->once()
        ->with('make:module', ['name' => 'billing'], Mockery::type(OutputInterface::class))
        ->andReturn(Command::SUCCESS);
    $this->artisan->shouldNotReceive('call')->with('modules:sync', Mockery::any(), Mockery::any());
    $this->modules->shouldReceive('getModulesPath')
        ->once()
        ->andReturn('/tmp/app-modules');
    $this->filesystem->shouldReceive('isFile')
        ->once()
        ->with($composerPath)
        ->andReturnTrue();
    $this->filesystem->shouldReceive('get')
        ->once()
        ->with($composerPath)
        ->andReturn('{"name":"acme/billing"}');
    $this->composer->shouldReceive('setWorkingPath')
        ->once()
        ->with(base_path())
        ->andReturnSelf();
    $this->composer->shouldReceive('findComposer')
        ->once()
        ->andReturn(['composer']);
    $this->processes->fake(fn () => $this->processes->result(
        errorOutput: 'Dependency resolution failed.',
        exitCode: 1,
    ));

    $tester = new CommandTester($this->command);
    $exitCode = $tester->execute(['name' => 'Billing']);

    expect($exitCode)->toBe(Command::FAILURE)
        ->and($tester->getDisplay())->toContain('Composer could not install module package [acme/billing].');
});
