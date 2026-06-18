<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_the_application_returns_a_successful_response(): void
    {
        // Route '/' redirect ke login — 302 adalah benar
        $response = $this->get('/');
        $response->assertRedirect('/login');
    }
}