<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_root_page_is_available(): void
    {
        $response = $this->get('/');

        $response->assertOk();
    }
}
