<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class UserTest extends TestCase
{
    use WithFaker;

    /**
     * A basic feature test example.
     *
     * @return void
     */
    public function testUserControllerMeMethod()
    {
        $user = User::factory()->create();
        $response = $this
            ->actingAs($user, 'api')
            ->get(route('user.me'));

        $response->assertOk();
        $response->assertJson([
            'data' => [
                'type' => 'user',
                'id' => $user->id,
                'attributes' => [
                    'name' => $user->name,
                ]
            ]
        ]);
    }

    /**
     * A basic feature test example.
     *
     * @return void
     */
    public function testUserControllerShowMethod()
    {
        $user = User::factory()->create();
        $response = $this
            ->actingAs($user, 'api')
            ->get(route('user.show', ['id' => $user->id]));

        $response->assertOk();
        $response->assertJson([
            'data' => [
                'type' => 'user',
                'id' => $user->id,
                'attributes' => [
                    'name' => $user->name,
                ]
            ]
        ]);
    }
}
