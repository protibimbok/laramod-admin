<?php

namespace Laramod\Admin\Tests\Unit;

use Composer\Composer;
use Composer\DependencyResolver\Operation\InstallOperation;
use Composer\DependencyResolver\Operation\OperationInterface;
use Composer\DependencyResolver\Operation\UpdateOperation;
use Composer\Installer\PackageEvent;
use Composer\Installer\PackageEvents;
use Composer\IO\BufferIO;
use Composer\Package\Package;
use Composer\Repository\InstalledArrayRepository;
use Composer\Script\ScriptEvents;
use Composer\Util\Filesystem;
use Laramod\Admin\ComposerPlugin;
use PHPUnit\Framework\TestCase;

class ComposerPluginTest extends TestCase
{
    protected string $basePath;

    protected string $workingDirectory;

    protected BufferIO $io;

    protected ComposerPlugin $plugin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->basePath = sys_get_temp_dir().'/laramod-admin-plugin-'.uniqid();
        $this->workingDirectory = (string) getcwd();

        mkdir($this->basePath);
        chdir($this->basePath);

        $this->plugin = new ComposerPlugin;
        $this->plugin->activate(new Composer, $this->io = new BufferIO);
    }

    protected function tearDown(): void
    {
        chdir($this->workingDirectory);

        (new Filesystem)->removeDirectory($this->basePath);

        parent::tearDown();
    }

    public function test_it_lists_the_module_and_runs_the_command_at_the_end_of_the_run_that_installed_the_package(): void
    {
        $this->fakeApplication();

        $this->assertSame([
            PackageEvents::POST_PACKAGE_INSTALL => 'rememberInstall',
            ScriptEvents::POST_INSTALL_CMD => 'init',
            ScriptEvents::POST_UPDATE_CMD => 'init',
        ], ComposerPlugin::getSubscribedEvents());

        $this->plugin->rememberInstall($this->packageEvent(new InstallOperation(new Package('laravel/framework', '13.0.0.0', '13.0.0'))));
        $this->plugin->init();

        $this->assertSame([], $this->ran());

        $this->plugin->rememberInstall($this->packageEvent(new InstallOperation($this->package())));
        $this->plugin->init();
        $this->plugin->init();

        $this->assertSame(['laramod:list --json', 'admin:init --ansi'], $this->ran());
        $this->assertStringContainsString('output of admin:init', $this->io->getOutput());

        $this->assertSame(<<<'PHP'
        <?php

        use Laramod\Admin\AdminModule;
        use Modules\Blog\BlogModule;

        return [
            BlogModule::class,
            AdminModule::class,
        ];

        PHP, file_get_contents($this->basePath.'/bootstrap/modules.php'));
    }

    public function test_the_first_module_replaces_the_placeholder_comment(): void
    {
        $this->fakeApplication(bootstrap: "<?php\n\nreturn [\n    //\n];\n");

        $this->install();

        $this->assertSame(
            "<?php\n\nuse Laramod\\Admin\\AdminModule;\n\nreturn [\n    AdminModule::class,\n];\n",
            file_get_contents($this->basePath.'/bootstrap/modules.php'),
        );
    }

    public function test_a_module_the_application_has_registered_is_not_listed_again(): void
    {
        $this->fakeApplication(modules: [['name' => 'admin', 'class' => 'Laramod\Admin\AdminModule']]);

        $bootstrap = file_get_contents($this->basePath.'/bootstrap/modules.php');

        $this->install();

        $this->assertSame(['laramod:list --json', 'admin:init --ansi'], $this->ran());
        $this->assertSame($bootstrap, file_get_contents($this->basePath.'/bootstrap/modules.php'));
    }

    public function test_a_module_that_shares_its_name_with_an_imported_one_is_listed_by_its_full_name(): void
    {
        $this->fakeApplication(bootstrap: "<?php\n\nuse Modules\\Admin\\AdminModule;\n\nreturn [\n    AdminModule::class,\n];\n");

        $this->install();

        $this->assertSame(
            "<?php\n\nuse Modules\\Admin\\AdminModule;\n\nreturn [\n    AdminModule::class,\n    Laramod\\Admin\\AdminModule::class,\n];\n",
            file_get_contents($this->basePath.'/bootstrap/modules.php'),
        );
    }

    public function test_an_update_of_the_package_does_not_run_the_command(): void
    {
        $this->fakeApplication();

        $this->plugin->rememberInstall($this->packageEvent(new UpdateOperation($this->package(), $this->package())));
        $this->plugin->init();

        $this->assertSame([], $this->ran());
    }

    public function test_it_tells_to_list_the_module_when_it_cannot(): void
    {
        $this->fakeApplication(bootstrap: "<?php\n\nreturn array_merge(\$core, \$extra);\n");

        $this->install();

        $this->assertSame(['laramod:list --json'], $this->ran());
        $this->assertStringContainsString(
            'list Laramod\Admin\AdminModule in bootstrap/modules.php, then run "php artisan admin:init"',
            $this->io->getOutput(),
        );
    }

    public function test_it_touches_nothing_when_the_modules_cannot_be_read(): void
    {
        $this->fakeApplication(modules: null);

        $bootstrap = file_get_contents($this->basePath.'/bootstrap/modules.php');

        $this->install();

        $this->assertSame(['laramod:list --json'], $this->ran());
        $this->assertSame($bootstrap, file_get_contents($this->basePath.'/bootstrap/modules.php'));
        $this->assertStringContainsString('then run "php artisan admin:init"', $this->io->getOutput());
    }

    public function test_it_does_nothing_outside_a_laravel_application(): void
    {
        $this->install();

        $this->assertSame('', $this->io->getOutput());
    }

    /**
     * Write the modules bootstrap file and an "artisan" that records what it is asked to run.
     *
     * @param  list<array<string, string>>|null  $modules  What "laramod:list --json" answers, null to have it fail.
     */
    protected function fakeApplication(
        string $bootstrap = "<?php\n\nuse Modules\\Blog\\BlogModule;\n\nreturn [\n    BlogModule::class,\n];\n",
        ?array $modules = [['name' => 'blog', 'class' => 'Modules\Blog\BlogModule']],
    ): void {
        mkdir($this->basePath.'/bootstrap');

        file_put_contents($this->basePath.'/bootstrap/modules.php', $bootstrap);
        file_put_contents($this->basePath.'/artisan', sprintf(<<<'PHP'
        <?php

        file_put_contents(__DIR__.'/ran', implode(' ', array_slice($argv, 1))."\n", FILE_APPEND);

        if ($argv[1] === 'laramod:list') {
            echo %s;

            exit(%d);
        }

        echo 'output of '.$argv[1];
        PHP, var_export(json_encode($modules), true), $modules === null ? 1 : 0));
    }

    /**
     * Have Composer install the package and reach the end of its run.
     */
    protected function install(): void
    {
        $this->plugin->rememberInstall($this->packageEvent(new InstallOperation($this->package())));
        $this->plugin->init();
    }

    /**
     * Get the Artisan commands that were run.
     *
     * @return list<string>
     */
    protected function ran(): array
    {
        return is_file($this->basePath.'/ran')
            ? file($this->basePath.'/ran', FILE_IGNORE_NEW_LINES)
            : [];
    }

    protected function package(): Package
    {
        return new Package('protibimbok/laramod-admin', '1.0.0.0', '1.0.0');
    }

    protected function packageEvent(OperationInterface $operation): PackageEvent
    {
        return new PackageEvent(
            PackageEvents::POST_PACKAGE_INSTALL, new Composer, $this->io, true, new InstalledArrayRepository, [$operation], $operation,
        );
    }
}
