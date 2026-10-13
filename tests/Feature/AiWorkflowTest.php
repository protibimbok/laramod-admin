<?php

namespace Laramod\Admin\Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Laramod\Admin\Tests\TestCase;

class AiWorkflowTest extends TestCase
{
    public function test_laramod_lists_the_workflow_of_the_module(): void
    {
        Artisan::call('laramod:list --json');

        $admin = collect(json_decode(Artisan::output(), true))->firstWhere('name', 'admin');

        $this->assertSame(realpath($this->root().'/ai-workflow/README.md'), realpath($admin['ai_workflow']));
        $this->assertContains('admin-ai', $admin['publish_tags']);
    }

    public function test_the_entry_point_lists_every_workflow(): void
    {
        $readme = file_get_contents($this->root().'/ai-workflow/README.md');

        foreach (glob($this->root().'/ai-workflow/workflows/*.md') as $workflow) {
            $this->assertStringContainsString('`'.basename($workflow).'`', $readme);
        }
    }

    protected function root(): string
    {
        return dirname(__DIR__, 2);
    }
}
