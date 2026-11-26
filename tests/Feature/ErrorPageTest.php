<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\HttpException;
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

    public function test_504_error_renders_custom_dark_page(): void
    {
        Route::middleware('web')->get('/__test-timeout', static function (): never {
            throw new HttpException(504);
        });

        $response = $this->get('/__test-timeout');

        $response->assertStatus(504);
        $response->assertSee(__('ui.gateway_timeout'))
            ->assertSee(__('ui.gateway_timeout_hint'))
            ->assertSee('btn-ghost', false);
        $response->assertViewIs('errors.504');
    }

    public function test_nginx_serves_a_custom_static_504_page(): void
    {
        $page = (string) file_get_contents(public_path('504.html'));
        $config = (string) file_get_contents(base_path('docker/nginx/default.conf'));

        $this->assertStringContainsString('background: #0f111a', $page);
        $this->assertStringContainsString('color: #ffffff', $page);
        $this->assertStringContainsString('504 Время ожидания истекло', $page);
        $this->assertStringContainsString('Вернуться на главную', $page);
        $this->assertStringContainsString('href="/"', $page);
        $this->assertStringContainsString('<svg', $page);
        $this->assertStringContainsString('error_page 504 /504.html;', $config);
        $this->assertStringContainsString('fastcgi_intercept_errors on;', $config);
    }
}
