<?php

namespace App\Interfaces;

use App\Models\User;
use Symfony\Component\HttpFoundation\RedirectResponse;

interface LoginServiceInterface
{
    /**
     * Redirect to OAuth authorization service URL.
     */
    public function redirect(string $provider): RedirectResponse;

    /**
     * Handle callback.
     */
    public function callback(string $provider, array $clientInfo): User;

    /**
     * Handle callback.
     */
    public function createToken(User $user): string;
}
