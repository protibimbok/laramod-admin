<?php

namespace Laramod\Admin\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Process;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'admin:init')]
class InitCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'admin:init
                            {--force : Overwrite the components that differ from the ones the admin is written against}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Set up React and shadcn/ui for the admin';

    /**
     * The steps that could not be applied and have to be done by hand.
     *
     * @var list<string>
     */
    protected array $manual = [];

    public function __construct(protected Filesystem $files)
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * Every step leaves what is already there alone, so the command is safe to run again, the way the Composer
     * plugin does whenever the package is installed. TypeScript is the application's to set up, and nothing is
     * touched before it is.
     */
    public function handle(): int
    {
        $this->manual = [];

        if (($missing = $this->missingTypeScript()) !== []) {
            $this->components->error('TypeScript is not set up. Set it up first, then run this command again:');
            $this->components->bulletList($missing);

            return self::FAILURE;
        }

        $this->report('node_modules/', $this->installPackages());
        $this->report('react', $this->addPackages(['react', 'react-dom']));
        $this->report('react (dev)', $this->addPackages(['@vitejs/plugin-react', '@types/react', '@types/react-dom'], dev: true));

        $this->report('vite.config.ts', $this->checkVitePlugin());
        $this->report('components.json', $shadcn = $this->setUpShadcn());
        $this->report('admin packages', $this->addPackages([
            'react-router@^7', '@base-ui/react', 'class-variance-authority', 'cn', 'lucide-react',
        ]));

        // What "shadcn init" has just written is not the user's work, so the admin's components replace it.
        $this->report('resources/js/components/ui/', $this->copyComponents($shadcn === 'created' || $this->option('force')));
        $this->report('tailwind', $this->addTailwindSource());

        if ($this->manual !== []) {
            $this->components->warn('Some steps could not be applied automatically:');
            $this->components->bulletList($this->manual);
        }

        return self::SUCCESS;
    }

    /**
     * Get what the application has yet to do to set up TypeScript: the package, its configuration and Vite's.
     *
     * @return list<string>
     */
    protected function missingTypeScript(): array
    {
        return array_keys(array_filter([
            'Add "typescript" to package.json' => $this->missingPackages(['typescript']) !== [],
            'Create tsconfig.json, with the "@/*" alias for resources/js' => ! $this->files->exists($this->laravel->basePath('tsconfig.json')),
            'Write the Vite configuration in vite.config.ts' => ! $this->files->exists($this->laravel->basePath('vite.config.ts')),
        ]));
    }

    /**
     * Install the application's npm packages when they have never been installed.
     */
    protected function installPackages(): string
    {
        if ($this->files->isDirectory($this->laravel->basePath('node_modules'))) {
            return 'exists';
        }

        return $this->shell('pnpm install') ? 'installed' : 'manual';
    }

    /**
     * Add the npm packages the application's package.json does not list yet.
     *
     * @param  list<string>  $packages
     */
    protected function addPackages(array $packages, bool $dev = false): string
    {
        if (($missing = $this->missingPackages($packages)) === []) {
            return 'exists';
        }

        return $this->shell('pnpm add '.($dev ? '-D ' : '').implode(' ', $missing)) ? 'installed' : 'manual';
    }

    /**
     * Get the npm packages the application's package.json does not list. A package may name the version to add: "vite@^8".
     *
     * @param  list<string>  $packages
     * @return list<string>
     */
    protected function missingPackages(array $packages): array
    {
        $manifest = $this->files->exists($path = $this->laravel->basePath('package.json'))
            ? json_decode($this->files->get($path), true)
            : [];

        $listed = ($manifest['dependencies'] ?? []) + ($manifest['devDependencies'] ?? []);

        return array_values(array_filter(
            $packages,
            fn (string $package): bool => ! isset($listed[preg_replace('/(?<!^)@.*$/', '', $package)]),
        ));
    }

    /**
     * Tell how to build React. The file is the user's own code, so it is never edited.
     */
    protected function checkVitePlugin(): string
    {
        if (str_contains($this->files->get($this->laravel->basePath('vite.config.ts')), '@vitejs/plugin-react')) {
            return 'exists';
        }

        return $this->manual('In vite.config.ts, import react from "@vitejs/plugin-react" and add react() to the plugins');
    }

    /**
     * Let shadcn/ui set itself up: components.json, its npm packages, the theme in app.css and lib/utils.ts.
     */
    protected function setUpShadcn(): string
    {
        if ($this->files->exists($this->laravel->basePath('components.json'))) {
            return 'exists';
        }

        return $this->shell('pnpm dlx shadcn@latest init --template laravel --defaults') ? 'created' : 'manual';
    }

    /**
     * Copy the shadcn/ui components the admin is written against into the application, where shadcn/ui keeps them.
     *
     * The application has one copy of a component, the admin's pages and the application's own use the same one.
     * A component that is there already and differs is the user's: it is kept, unless it may be overwritten.
     */
    protected function copyComponents(bool $overwrite): string
    {
        if (($aliases = $this->shadcn()['aliases'] ?? null) === null) {
            return 'skipped';
        }

        if (($aliases['ui'] ?? null) !== '@/components/ui' || ($aliases['hooks'] ?? null) !== '@/hooks') {
            return $this->manual(
                'The admin imports its components from "@/components/ui" and "@/hooks", which components.json aliases differently: copy stubs/components/ui and stubs/hooks of the package there yourself',
            );
        }

        $statuses = [];
        $stubs = dirname(__DIR__, 2).'/stubs';

        foreach ($this->files->allFiles($stubs) as $stub) {
            $target = 'resources/js/'.str_replace('\\', '/', $stub->getRelativePathname());

            $statuses[$target] = $this->copyComponent($stub->getPathname(), $this->laravel->basePath($target), $overwrite);
        }

        if ($conflicts = array_keys($statuses, 'conflict', true)) {
            $this->manual(sprintf(
                '%s differ%s from the version the admin is written against. "php artisan admin:init --force" overwrites %s',
                implode(', ', $conflicts), count($conflicts) === 1 ? 's' : '', count($conflicts) === 1 ? 'it' : 'them',
            ));
        }

        return match (true) {
            $conflicts !== [] => 'conflict',
            in_array('updated', $statuses, true) => 'updated',
            in_array('created', $statuses, true) => 'created',
            default => 'exists',
        };
    }

    /**
     * Copy one component, unless the application has it already.
     */
    protected function copyComponent(string $stub, string $target, bool $overwrite): string
    {
        // Line endings are the repository's business, so they make no difference.
        $normalize = fn (string $contents): string => str_replace("\r\n", "\n", $contents);

        if ($this->files->exists($target)) {
            if ($normalize($this->files->get($target)) === $normalize($this->files->get($stub))) {
                return 'exists';
            }

            if (! $overwrite) {
                return 'conflict';
            }
        }

        $status = $this->files->exists($target) ? 'updated' : 'created';

        $this->files->ensureDirectoryExists(dirname($target));
        $this->files->copy($stub, $target);

        return $status;
    }

    /**
     * Have Tailwind read the classes of the admin. It lives in the vendor directory, which Tailwind leaves out.
     */
    protected function addTailwindSource(): string
    {
        if (($css = $this->shadcn()['tailwind']['css'] ?? null) === null) {
            return 'skipped';
        }

        $css = trim(str_replace('\\', '/', $css), '/');
        $directories = dirname($css) === '.' ? 0 : substr_count(dirname($css), '/') + 1;
        $source = sprintf("@source '%svendor/protibimbok/laramod-admin/resources/js';", str_repeat('../', $directories));

        $contents = $this->files->exists($path = $this->laravel->basePath($css)) ? $this->files->get($path) : '';

        if (str_contains($contents, 'laramod-admin/resources/js')) {
            return 'exists';
        }

        // The line joins the other sources, or else the imports. The file is the user's, so it is edited as text.
        foreach (['source', 'import'] as $rule) {
            if (preg_match_all('/^@'.$rule.'\b[^\n]*;[ \t]*$/m', $contents, $matches, PREG_OFFSET_CAPTURE)) {
                [$line, $offset] = end($matches[0]);

                $this->files->put($path, substr_replace($contents, "\n".$source, $offset + strlen($line), 0));

                return 'updated';
            }
        }

        return $this->manual(sprintf('Add %s to %s', $source, $css));
    }

    /**
     * Get the application's shadcn/ui configuration, if shadcn/ui is set up.
     *
     * @return array<string, mixed>
     */
    protected function shadcn(): array
    {
        return $this->files->exists($path = $this->laravel->basePath('components.json'))
            ? (json_decode($this->files->get($path), true) ?? [])
            : [];
    }

    /**
     * Run a shell command in the application's base path. What a failed command printed is shown as it is.
     */
    protected function shell(string $command): bool
    {
        $this->line(sprintf('  <fg=gray>$ %s</>', $command));

        $result = Process::path($this->laravel->basePath())->forever()->run($command);

        if ($result->successful()) {
            return true;
        }

        $this->output->write($result->output().$result->errorOutput());
        $this->manual(sprintf('Run "%s" yourself', $command));

        return false;
    }

    /**
     * Remember a step that has to be done by hand.
     */
    protected function manual(string $instruction): string
    {
        $this->manual[] = $instruction;

        return 'manual';
    }

    /**
     * Report the outcome of a step.
     */
    protected function report(string $target, string $status): void
    {
        $color = match ($status) {
            'created', 'updated', 'installed' => 'green',
            'manual' => 'red',
            default => 'yellow',
        };

        $this->components->twoColumnDetail($target, sprintf('<fg=%s;options=bold>%s</>', $color, strtoupper($status)));
    }
}
