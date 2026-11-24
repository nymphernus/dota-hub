<?php

namespace Tests\Feature;

use Tests\TestCase;

class ErrorPageTest extends TestCase
{
    public function test_unknown_route_renders_the_custom_404_page(): void
    {
        $this->get('/this-page-does-not-exist')
            ->assertNotFound()
            ->assertSee(__('ui.not_found_title'))
            ->assertSee(__('ui.back_home'));
    }

    public function test_server_and_rate_limit_error_views_render(): void
    {
        $this->view('errors.500')
            ->assertSee(__('ui.server_error'))
            ->assertSee(__('ui.back_home'));

        $this->view('errors.429')
            ->assertSee(__('ui.too_many_requests'))
            ->assertSee(__('ui.back_home'));
    }
}
