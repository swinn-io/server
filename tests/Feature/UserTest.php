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
     * Me method for an authenticated user.
     *
     * @return void
     */
    public function testUserControllerMeMethodWithoutAuthentication()
    {
        $this->withoutExceptionHandling();
        $response = $this
            ->get(route('user.me'));

        $response->assertRedirect();
        $response->isRedirect(route('login'));
    }

    /**
     * Me method for an authenticated user.
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
     * Show method for a user id.
     *
     * @return void
     */
    public function testUserControllerShowMethodWithoutAuthentication()
    {
        $this->withoutExceptionHandling();
        $user = User::factory()->create();
        $response = $this
            ->get(route('user.show', ['id' => $user->id]));

        $response->assertRedirect();
        $response->isRedirect(route('login'));
    }

    /**
     * Show method for a user id.
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
