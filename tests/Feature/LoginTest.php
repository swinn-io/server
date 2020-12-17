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
        $this->artisan('passport:install');
    }

    /**
     * Test Socialite provider list page.
     *
     * @return void
     */
    public function testLoginControllerHomeMethod()
    {
        $response = $this->get(route('login'));

        $response->assertOk();
        $response->assertSee('GitHub');
    }

    /**
     * Test Socialite redirection callback.
     *
     * @return void
     */
    public function testLoginControllerRedirectMethod()
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
    public function testLoginControllerCallbackMethod()
    {
        $user = User::factory()->make();
        $socialiteUser = Mockery::mock(SocialiteUser::class);
        $socialiteUser->shouldReceive([
            'getId'       => Str::random(),
            'getName'     => $user->name,
            'getEmail'    => $this->faker->email,
            'getNickname' => Str::slug($user->name),
            'getAvatar'   => $this->faker->url,
        ])
            ->andSet('user', $user)
            ->andSet('token', Str::random(40))
            ->andSet('refreshToken', Str::random(40));

        Socialite::shouldReceive('driver->user')->andReturn($socialiteUser);

        $response = $this->get(route('login.callback', ['provider' => 'github']))->dump();
        $response->assertRedirect();
    }
}
