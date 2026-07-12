<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class FrontEndTest extends TestCase
{
    /**
     * Test if home page is there.
     *
     * @return void
     */
    public function test_page_controller_index_method()
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee(Config::string('app.name'));
    }
}
