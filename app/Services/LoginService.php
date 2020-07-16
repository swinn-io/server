<?php
namespace App\Services;

use App\Interfaces\LoginServiceInterface;
use App\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Laravel\Passport\Client;
use Laravel\Passport\ClientRepository;
use Laravel\Socialite\Contracts\User as UserContract;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse;

class LoginService implements LoginServiceInterface
{
    /**
     * Laravel Passport Client Repository.
     *
     * @var ClientRepository $clientRepository
     */
    private $clientRepository;

    /**
     * LoginService constructor.
     *
     * @param ClientRepository $repository
     */
    public function __construct(ClientRepository $repository)
    {
        $this->clientRepository = $repository;
    }

    /**
     * Redirect to OAuth authorization service URL.
     *
     * @param string $provider
     * @return RedirectResponse
     */
    public function redirect(string $provider): RedirectResponse
    {
        return Socialite::driver($provider)
            // Define custom scopes if needed under "services.{provider}"
            ->scopes(config("services.{$provider}.scopes") ?? '*')
            ->stateless()
            ->redirect();
    }

    /**
     * Handle callback.
     *
     * @param string $provider
     * @param array $clientInfo
     * @return User
     */
    public function callback(string $provider, array $clientInfo): User
    {
        $callback = Socialite::driver($provider)->stateless()->user();
        $user     = $this->user($provider, $callback);
        $this->client($user, $clientInfo);

        return $user->load('clients');
    }

    /**
     * Create a new user or update existing one.
     *
     * @param string $provider
     * @param UserContract $userContract
     * @return User
     */
    public function user(string $provider, UserContract $userContract): User
    {
        return User::updateOrCreate([
            'provider_name'  => $provider,                       // GitHub, LinkedIn, Google, Apple
            'provider_id'    => $userContract->getId(),          // uuid-0001-0002-0003
        ],[
            'name'           => $userContract->getName(),
            /**
             * Authorization service tokens and profile.
             * These tokens are used to retrieve data
             * from authorization server such as GitHub,
             * Twitter or Google.
             */
            'access_token'   => $userContract->token,            // TOKEN
            'refresh_token'  => $userContract->refreshToken,     // TOKEN - some providers have it
            'profile'        => $userContract->user,             // JSON profile data
        ]);
    }

    /**
     * Get or create client for user.
     *
     * @param User $user
     * @param array $clientInfo
     * @return Client
     */
    public function client(User $user, array $clientInfo): Client
    {
        $find = $this->clientRepository->forUser($user->id);

        return $find->first() ?? $this->clientRepository->create(
            $user->id,
            "{$user->provider_name}-{$user->provider_id}",
            Arr::get($clientInfo, 'redirect_uri'),
            $user->provider_name,
            true
        );
    }
}
