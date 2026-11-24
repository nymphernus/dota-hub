<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Tests must never reach the real network; unmatched fakes fail fast
        // instead of hanging on an external API timeout.
        Http::preventStrayRequests();
    }
}
