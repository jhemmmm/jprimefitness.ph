<?php

namespace Tests\Feature;

use Tests\TestCase;

class AsyncSearchSelectUiTest extends TestCase
{
    public function test_async_search_select_uses_bootstrap_theme_variables_for_dark_mode(): void
    {
        $contents = file_get_contents(resource_path('js/components/panel/_vendor/AsyncSearchSelect.vue'));

        $this->assertNotFalse($contents);
        $this->assertStringNotContainsString('bg-white', $contents);
        $this->assertStringContainsString('var(--bs-body-bg)', $contents);
        $this->assertStringContainsString('var(--bs-border-color-translucent)', $contents);
        $this->assertStringContainsString('var(--bs-body-color)', $contents);
        $this->assertStringContainsString('var(--bs-tertiary-bg)', $contents);
    }
}
