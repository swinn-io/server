<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use Mockery;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use WithFaker;

    /**
     * Setup testing.
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMockingConsoleOutput();
        $this->artisan('passport:install', ['--no-interaction' => true]);
    }

    /**
     * Test Socialite provider list page.
     *
     * @return void
     */
    public function test_login_controller_home_method()
    {
        $response = $this->get(route('login'));

        $response->assertOk();
        $response->assertViewIs('login');
        $response->assertSee("window.__PAGE__ = 'Login'", false);
    }

    /**
     * Test Socialite redirection callback.
     *
     * @return void
     */
    public function test_login_controller_redirect_method()
    {
        $response = $this->get(route('login.redirect', ['provider' => 'github']));

        $response->assertRedirect();
        $response->assertSee('Redirecting to');
    }

    /**
     * Test Socialite authentication callback.
     *
     * @return void
     */
    public function test_login_controller_callback_method()
    {
        $user = User::factory()->make();
        $socialiteUser = Mockery::mock(SocialiteUser::class);
        $socialiteUser->shouldReceive('getId')->andReturn(Str::random())
            ->andSet('user', $user)
            ->andSet('token', Str::random(40))
            ->andSet('refreshToken', Str::random(40));
        $socialiteUser->shouldReceive('getName')->andReturn($user->name);
        $socialiteUser->shouldReceive('getEmail')->andReturn($this->faker->email());
        $socialiteUser->shouldReceive('getNickname')->andReturn(Str::slug($user->name));
        $socialiteUser->shouldReceive('getAvatar')->andReturn($this->faker->url());

        Socialite::shouldReceive('driver->user')->andReturn($socialiteUser);

        $response = $this->get(route('login.callback', ['provider' => 'github']));
        $response->assertRedirect();
    }

    /**
     * Test logout revokes the user's access tokens.
     *
     * @return void
     */
    public function test_login_controller_logout_method()
    {
        $user = User::factory()->create();
        $user->createToken('Test Token');

        $this->assertFalse($user->tokens()->where('revoked', true)->exists());

        $response = $this->actingAs($user)->post(route('logout'));

        $response->assertRedirect('/');
        $this->assertGuest();
        $this->assertTrue($user->tokens()->where('revoked', false)->doesntExist());
    }
}
