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
    protected $signature = 'admin:init';

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
        $this->report('components.json', $this->setUpShadcn());

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
     * Get the npm packages the application's package.json does not list.
     *
     * @param  list<string>  $packages
     * @return list<string>
     */
    protected function missingPackages(array $packages): array
    {
        $manifest = $this->files->exists($path = $this->laravel->basePath('package.json'))
            ? json_decode($this->files->get($path), true)
            : [];

        return array_values(array_diff($packages, array_keys(
            ($manifest['dependencies'] ?? []) + ($manifest['devDependencies'] ?? []),
        )));
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
