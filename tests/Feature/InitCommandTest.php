<?php

namespace Laramod\Admin\Tests\Feature;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;
use Laramod\Admin\Tests\TestCase;

class InitCommandTest extends TestCase
{
    protected const string SHADCN = 'pnpm dlx shadcn@latest init --template laravel --defaults';

    protected const string STUBS = __DIR__.'/../../stubs';

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

    public function test_it_sets_up_react_shadcn_and_the_admin(): void
    {
        $ran = [];

        Process::fake(function (PendingProcess $process) use (&$ran) {
            $this->assertSame($this->basePath, $process->path);

            if (($ran[] = $process->command) === self::SHADCN) {
                $this->shadcn();
            }

            return Process::result();
        });

        $this->artisan('admin:init')
            ->expectsOutputToContain('import react from "@vitejs/plugin-react"')
            ->doesntExpectOutputToContain('differs from the version')
            ->assertSuccessful();

        $this->assertSame([
            'pnpm install',
            'pnpm add react react-dom',
            'pnpm add -D @vitejs/plugin-react @types/react @types/react-dom',
            self::SHADCN,
            'pnpm add react-router@^7 @base-ui/react class-variance-authority cn lucide-react',
        ], $ran);

        $this->assertSame($this->composer, file_get_contents($this->basePath.'/composer.json'));

        // The button "shadcn init" has just written is replaced, as every component is the one the admin is written against.
        foreach ((new Filesystem)->allFiles(self::STUBS) as $stub) {
            $this->assertFileEquals($stub->getPathname(), $this->basePath.'/resources/js/'.$stub->getRelativePathname());
        }

        $this->assertStringContainsString(
            "@source '../../storage/framework/views/*.php';\n@source '../../vendor/protibimbok/laramod-admin/resources/js';\n\n@theme",
            file_get_contents($this->basePath.'/resources/css/app.css'),
        );
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
        $this->shadcn(css: $css = "@import 'tailwindcss';\n@source '../../vendor/protibimbok/laramod-admin/resources/js';\n");
        $files->copyDirectory(self::STUBS, $this->basePath.'/resources/js');
        $files->put($this->basePath.'/vite.config.ts', "import react from '@vitejs/plugin-react';\n");
        $files->put($this->basePath.'/package.json', json_encode([
            'dependencies' => [
                '@base-ui/react' => '^1.0.0', 'class-variance-authority' => '^0.7.0', 'cn' => '^0.3.0',
                'lucide-react' => '^1.0.0', 'react' => '^19.0.0', 'react-dom' => '^19.0.0', 'react-router' => '^7.0.0',
            ],
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

        $this->assertSame($css, file_get_contents($this->basePath.'/resources/css/app.css'));
    }

    public function test_a_component_the_application_has_changed_is_kept_until_it_may_be_overwritten(): void
    {
        $this->shadcn();

        $files = new Filesystem;
        $files->ensureDirectoryExists($this->basePath.'/resources/js/hooks');
        $files->put($button = $this->basePath.'/resources/js/components/ui/button.tsx', $mine = "export const Button = 'mine'\n");
        $files->put(
            $hook = $this->basePath.'/resources/js/hooks/use-mobile.ts',
            str_replace("\n", "\r\n", file_get_contents(self::STUBS.'/hooks/use-mobile.ts')),
        );

        $this->artisan('admin:init')
            ->expectsOutputToContain('resources/js/components/ui/button.tsx differs from the version the admin is written against. "php artisan admin:init --force" overwrites it')
            ->assertSuccessful();

        $this->assertSame($mine, file_get_contents($button));
        $this->assertStringContainsString("\r\n", file_get_contents($hook));
        $this->assertFileEquals(self::STUBS.'/components/ui/sidebar.tsx', $this->basePath.'/resources/js/components/ui/sidebar.tsx');

        $this->artisan('admin:init', ['--force' => true])
            ->doesntExpectOutputToContain('differs from the version')
            ->assertSuccessful();

        $this->assertFileEquals(self::STUBS.'/components/ui/button.tsx', $button);
    }

    public function test_components_are_not_copied_where_the_admin_would_not_find_them(): void
    {
        $this->shadcn(aliases: ['ui' => '@/ui', 'hooks' => '@/hooks']);

        $this->artisan('admin:init')
            ->expectsOutputToContain('which components.json aliases differently')
            ->assertSuccessful();

        $this->assertDirectoryDoesNotExist($this->basePath.'/resources/js/ui');
        $this->assertFileDoesNotExist($this->basePath.'/resources/js/components/ui/sidebar.tsx');
    }

    public function test_the_tailwind_source_joins_the_imports_of_a_file_without_sources(): void
    {
        $this->shadcn(css: "@import 'tailwindcss';\n@import \"tw-animate-css\";\n\n@theme {\n}\n", path: 'resources/app.css');

        $this->artisan('admin:init')->assertSuccessful();

        $this->assertSame(
            "@import 'tailwindcss';\n@import \"tw-animate-css\";\n@source '../vendor/protibimbok/laramod-admin/resources/js';\n\n@theme {\n}\n",
            file_get_contents($this->basePath.'/resources/app.css'),
        );
    }

    public function test_it_tells_which_tailwind_source_to_add_when_it_finds_no_place_for_it(): void
    {
        $this->shadcn(css: $css = "/* empty */\n");

        $this->artisan('admin:init')
            ->expectsOutputToContain("Add @source '../../vendor/protibimbok/laramod-admin/resources/js'; to resources/css/app.css")
            ->assertSuccessful();

        $this->assertSame($css, file_get_contents($this->basePath.'/resources/css/app.css'));
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

    /**
     * Write what "shadcn init" leaves behind: its configuration, the stylesheet it extends and its button.
     *
     * @param  array<string, string>  $aliases
     */
    protected function shadcn(
        array $aliases = ['ui' => '@/components/ui', 'hooks' => '@/hooks'],
        string $css = "@import 'tailwindcss';\n\n@source '../../storage/framework/views/*.php';\n\n@theme {\n}\n",
        string $path = 'resources/css/app.css',
    ): void {
        $files = new Filesystem;

        $files->put($this->basePath.'/components.json', json_encode(['tailwind' => ['css' => $path], 'aliases' => $aliases]));

        $files->ensureDirectoryExists(dirname($this->basePath.'/'.$path));
        $files->put($this->basePath.'/'.$path, $css);

        $files->ensureDirectoryExists($this->basePath.'/resources/js/components/ui');
        $files->put($this->basePath.'/resources/js/components/ui/button.tsx', "export const Button = 'shadcn'\n");
    }
}
