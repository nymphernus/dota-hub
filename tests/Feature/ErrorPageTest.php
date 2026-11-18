<?php

namespace Tests\Feature;

use Tests\TestCase;

class ErrorPageTest extends TestCase
{
    public function test_unknown_route_renders_the_custom_404_page(): void
    {
        $this->get('/this-page-does-not-exist')
            ->assertNotFound()
            ->assertSee('Игрок или матч не найден')
            ->assertSee('Вернуться на главную');
    }

    public function test_server_and_rate_limit_error_views_render(): void
    {
        $this->view('errors.500')
            ->assertSee('Ошибка сервера')
            ->assertSee('Вернуться на главную');

        $this->view('errors.429')
            ->assertSee('Слишком много запросов')
            ->assertSee('Вернуться на главную');
    }
}
