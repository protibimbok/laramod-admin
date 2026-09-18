<?php

namespace Laramod\Admin\Tests\Feature;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;
use Laramod\Admin\Tests\TestCase;

class InitCommandTest extends TestCase
{
    protected const string SHADCN = 'pnpm dlx shadcn@latest init --template laravel --defaults';

    protected string $basePath;

    /**
     * The application's composer.json, which the command must never touch.
     */
    protected string $composer = "{\n    \"name\": \"laravel/laravel\",\n    \"scripts\": {}\n}\n";

    protected function setUp(): void
    {
        parent::setUp();

        $this->basePath = sys_get_temp_dir().'/laramod-admin-init-'.uniqid();

        $files = new Filesystem;
        $files->makeDirectory($this->basePath, recursive: true);
        $files->put($this->basePath.'/composer.json', $this->composer);
        $files->put($this->basePath.'/package.json', <<<'JSON'
        {
            "private": true,
            "devDependencies": {
                "typescript": "^7.0.0",
                "vite": "^8.0.0"
            }
        }

        JSON);
        $files->put($this->basePath.'/tsconfig.json', "{}\n");
        $files->put($this->basePath.'/vite.config.ts', "import laravel from 'laravel-vite-plugin';\n");

        $this->app->setBasePath($this->basePath);

        Process::fake();
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->basePath);

        parent::tearDown();
    }

    public function test_it_sets_up_react_and_shadcn(): void
    {
        $ran = [];

        Process::fake(function (PendingProcess $process) use (&$ran) {
            $this->assertSame($this->basePath, $process->path);

            $ran[] = $process->command;

            return Process::result();
        });

        $this->artisan('admin:init')
            ->expectsOutputToContain('import react from "@vitejs/plugin-react"')
            ->assertSuccessful();

        $this->assertSame([
            'pnpm install',
            'pnpm add react react-dom',
            'pnpm add -D @vitejs/plugin-react @types/react @types/react-dom',
            self::SHADCN,
        ], $ran);

        $this->assertSame($this->composer, file_get_contents($this->basePath.'/composer.json'));
    }

    public function test_it_fails_before_touching_anything_when_typescript_is_not_set_up(): void
    {
        rename($this->basePath.'/vite.config.ts', $this->basePath.'/vite.config.js');

        $this->artisan('admin:init')
            ->expectsOutputToContain('TypeScript is not set up')
            ->expectsOutputToContain('Write the Vite configuration in vite.config.ts')
            ->assertFailed();

        rename($this->basePath.'/vite.config.js', $this->basePath.'/vite.config.ts');
        rename($this->basePath.'/tsconfig.json', $this->basePath.'/tsconfig.bak');

        $this->artisan('admin:init')
            ->expectsOutputToContain('Create tsconfig.json')
            ->assertFailed();

        rename($this->basePath.'/tsconfig.bak', $this->basePath.'/tsconfig.json');
        file_put_contents($this->basePath.'/package.json', '{"devDependencies": {"vite": "^8.0.0"}}');

        $this->artisan('admin:init')
            ->expectsOutputToContain('Add "typescript" to package.json')
            ->assertFailed();

        Process::assertNothingRan();
    }

    public function test_it_leaves_alone_what_is_already_set_up(): void
    {
        $files = new Filesystem;
        $files->makeDirectory($this->basePath.'/node_modules');
        $files->put($this->basePath.'/components.json', '{}');
        $files->put($this->basePath.'/vite.config.ts', "import react from '@vitejs/plugin-react';\n");
        $files->put($this->basePath.'/package.json', json_encode([
            'dependencies' => ['react' => '^19.0.0', 'react-dom' => '^19.0.0'],
            'devDependencies' => [
                '@types/react' => '^19.0.0',
                '@types/react-dom' => '^19.0.0',
                '@vitejs/plugin-react' => '^6.0.0',
                'typescript' => '^7.0.0',
            ],
        ]));

        $this->artisan('admin:init')
            ->doesntExpectOutputToContain('could not be applied')
            ->assertSuccessful();

        Process::assertNothingRan();
    }

    public function test_it_only_adds_the_packages_that_are_missing(): void
    {
        file_put_contents($this->basePath.'/package.json', json_encode([
            'dependencies' => ['react' => '^19.0.0'],
            'devDependencies' => ['typescript' => '^7.0.0', '@types/react' => '^19.0.0'],
        ]));

        $this->artisan('admin:init')->assertSuccessful();

        Process::assertRan('pnpm add react-dom');
        Process::assertRan('pnpm add -D @vitejs/plugin-react @types/react-dom');
    }

    public function test_a_failed_command_is_reported_without_failing(): void
    {
        Process::fake(fn (PendingProcess $process) => $process->command === self::SHADCN
            ? Process::result(errorOutput: 'No Tailwind CSS configuration found.', exitCode: 1)
            : Process::result());

        $this->artisan('admin:init')
            ->expectsOutputToContain('No Tailwind CSS configuration found.')
            ->expectsOutputToContain('Run "'.self::SHADCN.'" yourself')
            ->assertSuccessful();
    }
}
