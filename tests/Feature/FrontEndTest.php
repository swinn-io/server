<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class FrontEndTest extends TestCase
{
    /**
     * Test if home page is there.
     *
     * @return void
     */
    public function testPageControllerIndexMethod()
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee(config('app.name'));
    }
}
