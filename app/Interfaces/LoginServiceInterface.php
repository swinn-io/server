<?php

namespace App\Interfaces;

use App\Models\User;
use Laravel\Passport\Client;
use Laravel\Socialite\Contracts\User as UserContract;
use Symfony\Component\HttpFoundation\RedirectResponse;

interface LoginServiceInterface
{
    /**
     * Redirect to OAuth authorization service URL.
     */
    public function redirect(string $provider): RedirectResponse;

    /**
     * Handle callback.
     *
     * @param  array<string, mixed>  $clientInfo
     */
    public function callback(string $provider, array $clientInfo): User;

    /**
     * Handle callback.
     */
    public function createToken(User $user): string;

    /**
     * Create a new user or update existing one.
     */
    public function user(string $provider, UserContract $userContract): User;

    /**
     * Get or create client for user.
     *
     * @param  array<string, mixed>  $clientInfo
     */
    public function client(User $user, array $clientInfo): Client;
}
