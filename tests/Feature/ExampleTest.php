<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_the_application_returns_a_not_found_response_for_root_path(): void
    {
        $response = $this->get('/');

        $response->assertNotFound();
    }
}
