<?php

namespace App\Interfaces;

use App\Models\User;
use Symfony\Component\HttpFoundation\RedirectResponse;

interface LoginServiceInterface
{
    /**
     * Redirect to OAuth authorization service URL.
     *
     * @param  string  $provider
     * @return RedirectResponse
     */
    public function redirect(string $provider): RedirectResponse;

    /**
     * Handle callback.
     *
     * @param  string  $provider
     * @param  array  $clientInfo
     * @return User
     */
    public function callback(string $provider, array $clientInfo): User;

    /**
     * Handle callback.
     *
     * @param  User  $user
     * @return string
     */
    public function createToken(User $user): string;
}
