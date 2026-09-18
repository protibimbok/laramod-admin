<?php

namespace Laramod\Admin;

use Composer\Composer;
use Composer\DependencyResolver\Operation\InstallOperation;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\Installer\PackageEvent;
use Composer\Installer\PackageEvents;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\ScriptEvents;
use Composer\Util\ProcessExecutor;
use Symfony\Component\Process\PhpExecutableFinder;

/**
 * Lists the module and runs "php artisan admin:init" once Composer has installed the package.
 *
 * Composer only runs the scripts of the application, never those of a package, so a plugin is the one way
 * to do this without the application's composer.json being involved.
 */
class ComposerPlugin implements EventSubscriberInterface, PluginInterface
{
    protected const string PACKAGE = 'protibimbok/laramod-admin';

    protected IOInterface $io;

    /**
     * Whether the package was installed during this run of Composer.
     */
    protected bool $installed = false;

    public function activate(Composer $composer, IOInterface $io): void
    {
        $this->io = $io;
    }

    public function deactivate(Composer $composer, IOInterface $io): void
    {
        //
    }

    public function uninstall(Composer $composer, IOInterface $io): void
    {
        //
    }

    /**
     * The command is run at the very end, when the autoloader and Laravel's package manifest are in place.
     *
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            PackageEvents::POST_PACKAGE_INSTALL => 'rememberInstall',
            ScriptEvents::POST_INSTALL_CMD => 'init',
            ScriptEvents::POST_UPDATE_CMD => 'init',
        ];
    }

    /**
     * Remember that the package was installed: "composer require", or "composer install" on a fresh clone.
     */
    public function rememberInstall(PackageEvent $event): void
    {
        $operation = $event->getOperation();

        if ($operation instanceof InstallOperation && $operation->getPackage()->getName() === self::PACKAGE) {
            $this->installed = true;
        }
    }

    /**
     * List the module, then run the command. What it reports is for the user to read, it never fails Composer.
     */
    public function init(): void
    {
        if (! $this->installed || ! is_file('artisan')) {
            return;
        }

        $this->installed = false;

        // The command only exists once the application lists the module.
        if (! $this->listModule()) {
            $this->io->writeError(sprintf(
                '<warning>%s: list %s in bootstrap/modules.php, then run "php artisan admin:init".</warning>',
                self::PACKAGE, AdminModule::class,
            ));

            return;
        }

        $this->io->writeError('> php artisan admin:init');

        $this->artisan('admin:init --ansi');
    }

    /**
     * List the module in the application's modules bootstrap file, the way "make:module" lists a new module,
     * unless the application has registered it already. Tells whether the module is registered afterwards.
     */
    protected function listModule(): bool
    {
        $this->artisan('laramod:list --json', $modules);

        if (! is_array($modules = json_decode((string) $modules, true))) {
            return false;
        }

        if (in_array(AdminModule::class, array_column($modules, 'class'), true)) {
            return true;
        }

        $contents = is_file($path = 'bootstrap/modules.php') ? (string) file_get_contents($path) : '';
        $basename = fn (string $class): string => substr((string) strrchr('\\'.$class, '\\'), 1);

        preg_match_all('/^use ([^;]+);[ \t]*\R/m', $contents, $matches);

        // A class that shares its name with an imported one is listed by its full name instead.
        $imported = ! in_array($basename(AdminModule::class), array_map($basename, $matches[1]), true);

        // The class is inserted as text, because the order of the list and its comments belong to the user.
        $updated = preg_replace_callback(
            '#(?:^[ \t]*//[ \t]*\R)?^\];#m',
            fn (): string => sprintf("    %s::class,\n];", $imported ? $basename(AdminModule::class) : AdminModule::class),
            $contents, 1, $count,
        );

        if ($count === 0) {
            return false;
        }

        if ($imported) {
            $imports = [...$matches[1], AdminModule::class];

            sort($imports, SORT_STRING | SORT_FLAG_CASE);

            $updated = preg_replace(
                '/^<\?php\s*/',
                "<?php\n\n".implode('', array_map(fn (string $import): string => "use {$import};\n", $imports))."\n",
                (string) preg_replace('/^use [^;]+;[ \t]*\R/m', '', (string) $updated), 1,
            );
        }

        file_put_contents($path, $updated);

        $this->io->writeError(sprintf('%s: module listed in bootstrap/modules.php', self::PACKAGE));

        return true;
    }

    /**
     * Run an Artisan command of the application Composer is working on and get its exit code.
     *
     * The output is shown as it comes, unless the caller asks for it.
     */
    protected function artisan(string $command, ?string &$output = null): int
    {
        $command = ProcessExecutor::escape((new PhpExecutableFinder)->find() ?: 'php').' artisan '.$command;

        $process = new ProcessExecutor($this->io);

        return func_num_args() > 1 ? $process->execute($command, $output) : $process->execute($command);
    }
}
