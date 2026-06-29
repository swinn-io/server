<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class UserTest extends TestCase
{
    use WithFaker;

    /**
     * Me method for an authenticated user.
     *
     * @return void
     */
    public function test_user_controller_me_method()
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
                ],
            ],
        ]);
    }

    /**
     * Show method for a user id.
     *
     * @return void
     */
    public function test_user_controller_show_method()
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
                ],
            ],
        ]);
    }
}
