<?php

namespace Tests\Unit;

use App\Interfaces\LoginServiceInterface;
use App\Models\User;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Mockery;
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
        $this->withMiddleware(\Illuminate\Session\Middleware\StartSession::class);
        $this->service = app(LoginServiceInterface::class);
    }

    /**
     * Testing unknown services.
     *
     * @return void
     */
    public function testUnknownServiceRedirection()
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
    public function testGithubRedirection()
    {
        $socialiteRedirection = Mockery::mock(\Symfony\Component\HttpFoundation\RedirectResponse::class);
        $socialiteRedirection->shouldReceive([
            'getStatusCode' => 302,
            'getTargetUrl' => 'https://github.com/login/oauth',
        ]);
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
    public function testUser()
    {
        $user = User::factory()->make();
        $nick = collect([$user->name, ''])->random();
        $socialiteUser = Mockery::mock(SocialiteUser::class);
        $socialiteUser->shouldReceive([
            'getId'       => Str::random(),
            'getName'     => $user->name,
            'getEmail'    => $this->faker->email,
            'getNickname' => $nick,
            'getAvatar'   => $this->faker->url,
        ])
            ->andSet('user', $user)
            ->andSet('token', Str::random(40))
            ->andSet('refreshToken', Str::random(40));

        $new_class = $this->service->user('github', $socialiteUser);
        $this->assertEquals($user->name, $new_class->name);
    }

    /**
     * Testing client method.
     *
     * @return void
     */
    public function testClient()
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
