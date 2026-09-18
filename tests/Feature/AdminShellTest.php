<?php

namespace Laramod\Admin\Tests\Feature;

use Illuminate\Foundation\Auth\User;
use Laramod\Admin\Tests\Fixtures\DashboardModule;
use Laramod\Admin\Tests\TestCase;
use Laramod\Facades\Modules;
use Orchestra\Testbench\Attributes\DefineEnvironment;
use Orchestra\Testbench\Attributes\DefineRoute;

class AdminShellTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    /**
     * Define the routes of the application the admin is installed in.
     */
    protected function defineRoutes($router): void
    {
        $router->get('login', fn () => 'login')->name('login');
        $router->get('admin/ping', fn () => 'pong');
    }

    protected function usesLogoutRoute($router): void
    {
        $router->post('logout', fn () => 'bye')->name('logout');
    }

    protected function usesCustomPath($app): void
    {
        $app['config']->set('modules.admin.path', '/backoffice/');
    }

    public function test_guests_are_sent_to_the_login_page(): void
    {
        $this->get('admin')->assertRedirect('login');
        $this->get('admin/posts/1/edit')->assertRedirect('login');
    }

    public function test_every_url_beneath_the_path_shows_the_page_the_admin_mounts_in(): void
    {
        $this->actingAs($this->user());

        foreach (['admin', 'admin/posts', 'admin/posts/1/edit'] as $url) {
            $this->get($url)
                ->assertOk()
                ->assertViewIs('admin::app')
                ->assertSee('<div id="admin" data-boot="', escape: false);
        }
    }

    public function test_the_page_tells_the_admin_where_it_lives_and_who_is_looking(): void
    {
        $boot = $this->actingAs($this->user())->get('admin/posts')->viewData('boot');

        $this->assertSame('Laravel', $boot['appName']);
        $this->assertSame('/admin', $boot['basePath']);
        $this->assertSame(['id' => 7, 'name' => 'Saad', 'email' => 'saad@example.com'], $boot['viewer']);
    }

    public function test_the_page_offers_no_sign_out_without_a_logout_route(): void
    {
        $this->assertNull($this->actingAs($this->user())->get('admin')->viewData('boot')['logoutUrl']);
    }

    #[DefineRoute('usesLogoutRoute')]
    public function test_the_page_offers_the_logout_route_of_the_application(): void
    {
        $this->assertSame('http://localhost/logout', $this->actingAs($this->user())->get('admin')->viewData('boot')['logoutUrl']);
    }

    public function test_the_page_loads_the_dashboard_scripts_of_the_modules(): void
    {
        Modules::register(new DashboardModule(
            'blog', ['resources/js/dashboard.tsx'], ['resources/js/dashboard.tsx'], ['https://cdn.example.com/chart.js'],
        ));

        $response = $this->actingAs($this->user())->get('admin');

        $this->assertSame('Modules/Blog/resources/js/dashboard.tsx', $response->viewData('entries')[0]);
        $this->assertStringEndsWith('resources/js/main.tsx', $response->viewData('entries')[1]);

        $response->assertSee('<script type="module" src="https://cdn.example.com/chart.js"></script>', escape: false);
    }

    public function test_a_route_beneath_the_same_path_takes_precedence(): void
    {
        $this->actingAs($this->user())->get('admin/ping')->assertOk()->assertSee('pong');
    }

    public function test_the_admin_does_not_answer_outside_its_path(): void
    {
        $this->actingAs($this->user())->get('administrator')->assertNotFound();
    }

    #[DefineEnvironment('usesCustomPath')]
    public function test_the_path_is_configurable(): void
    {
        $this->actingAs($this->user());

        $this->assertSame('/backoffice', $this->get('backoffice/posts')->assertOk()->viewData('boot')['basePath']);
        $this->get('admin')->assertNotFound();
    }

    protected function user(): User
    {
        return (new User)->forceFill(['id' => 7, 'name' => 'Saad', 'email' => 'saad@example.com', 'password' => 'secret']);
    }
}
