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
     */
    private ClientRepository $clientRepository;

    /**
     * LoginService constructor.
     */
    public function __construct(ClientRepository $repository)
    {
        $this->clientRepository = $repository;
    }

    /**
     * Redirect to OAuth authorization service URL.
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
     */
    public function callback(string $provider, array $clientInfo): User
    {
        $profile = Socialite::driver($provider)->user();

        return $this->user($provider, $profile);
    }

    /**
     * Handle callback.
     */
    public function createToken(User $user): string
    {
        $tokenName = config('app.name').' Token - '.now();

        return $user->createToken($tokenName)->accessToken;
    }

    /**
     * Create a new user or update existing one.
     */
    public function user(string $provider, UserContract $userContract): User
    {
        return User::updateOrCreate([
            'provider_name' => $provider,                       // GitHub, LinkedIn, Google, Apple
            'provider_id' => $userContract->getId(),          // unsignedBigInteger, uuid
        ], [
            'name' => $userContract->getName() ?? $userContract->getNickname(),
            /**
             * E-mails, tokens and profile will be synced.
             * E-mail is for e-mail notifications.
             * Tokens for retrieve data from authorization
             * server such as GitHub, Twitter or Google.
             */
            'email' => $userContract->getEmail(),       // OAuth provider e-mail address
            'notify_via' => ['broadcast'],                   // Default notification preference
            'access_token' => $userContract->token,            // TOKEN
            'refresh_token' => $userContract->refreshToken,     // TOKEN - some providers have it
            'profile' => $userContract->user,             // JSON profile data
        ]);
    }

    /**
     * Get or create client for user.
     */
    public function client(User $user, array $clientInfo): Client
    {
        $find = $user->oauthApps()->where('revoked', false)->orderBy('name');

        return $find->first() ?? $this->clientRepository->createAuthorizationCodeGrantClient(
            "{$user->provider_name}-{$user->provider_id}",
            array_filter([Arr::get($clientInfo, 'redirect_uri')]),
            false,
            $user
        );
    }
}
