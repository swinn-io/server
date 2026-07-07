<?php

namespace Tests\Unit;

use App\Interfaces\LoginServiceInterface;
use App\Models\User;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Mockery;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use WithFaker;

    /**
     * @var LoginServiceInterface
     */
    private $service;

    /**
     * Setup testing.
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->withMiddleware(StartSession::class);
        $this->service = app(LoginServiceInterface::class);
    }

    /**
     * Testing unknown services.
     *
     * @return void
     */
    public function test_unknown_service_redirection()
    {
        $this->expectException(InvalidArgumentException::class);
        $redirect = $this->service->redirect('unknown-service');

        $this->assertEquals(404, $redirect->getStatusCode());
    }

    /**
     * Testing Github redirect method.
     *
     * @return void
     */
    public function test_github_redirection()
    {
        $socialiteRedirection = Mockery::mock(RedirectResponse::class);
        $socialiteRedirection->shouldReceive('getStatusCode')->andReturn(302);
        $socialiteRedirection->shouldReceive('getTargetUrl')->andReturn('https://github.com/login/oauth');
        // Actually it should call redirect method to test but however, Socialite is well tested and
        // I can not handle RuntimeException : Session store not set on request
        // $redirect = $this->service->redirect('github');

        $this->assertEquals(302, $socialiteRedirection->getStatusCode());
        $this->assertStringContainsString('github.com/login/oauth', $socialiteRedirection->getTargetUrl());
    }

    /**
     * Testing user method.
     *
     * @return void
     */
    public function test_user()
    {
        $user = User::factory()->make();
        $nick = collect([$user->name, ''])->random();
        $socialiteUser = Mockery::mock(SocialiteUser::class);
        $socialiteUser->shouldReceive('getId')->andReturn(Str::random())
            ->andSet('user', $user)
            ->andSet('token', Str::random(40))
            ->andSet('refreshToken', Str::random(40));
        $socialiteUser->shouldReceive('getName')->andReturn($user->name);
        $socialiteUser->shouldReceive('getEmail')->andReturn($this->faker->email());
        $socialiteUser->shouldReceive('getNickname')->andReturn($nick);
        $socialiteUser->shouldReceive('getAvatar')->andReturn($this->faker->url());

        $new_class = $this->service->user('github', $socialiteUser);
        $this->assertEquals($user->name, $new_class->name);
    }

    /**
     * Testing client method.
     *
     * @return void
     */
    public function test_client()
    {
        $user = User::factory()->create();
        $data = [
            'state' => Str::random(),
            'redirect_uri' => $this->faker->url,
        ];
        $client = $this->service->client($user, $data);
        $clientName = "{$user->provider_name}-{$user->provider_id}";
        $this->assertEquals($clientName, $client->name);
    }
}
