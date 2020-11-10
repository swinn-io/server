<?php

namespace App\Services;

use App\Interfaces\LoginServiceInterface;
use App\Models\User;
use Illuminate\Support\Arr;
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
     * @var ClientRepository
     */
    private ClientRepository $clientRepository;

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
        $profile = Socialite::driver($provider)->user();
        return $this->user($provider, $profile);
    }

    /**
     * Handle callback.
     *
     * @param User $user
     * @return string
     */
    public function createToken(User $user): string
    {
        return $user->createToken(config('app.name') . ' Token')->accessToken;
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
            'provider_id'    => $userContract->getId(),          // unsignedBigInteger, uuid
        ], [
            'name'           => $userContract->getName() ?? $userContract->getNickname(),
            /**
             * E-mails, tokens and profile will be synced.
             * E-mail is for e-mail notifications.
             * Tokens for retrieve data from authorization
             * server such as GitHub, Twitter or Google.
             */
            'email'          => $userContract->getEmail(),       // OAuth provider e-mail address
            'notify_via'     => ['broadcast'],                   // Default notification preference
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
                false,
                false,
                false
            );
    }
}
