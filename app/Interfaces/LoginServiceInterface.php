<?php
namespace App\Interfaces;

use App\User;
use Symfony\Component\HttpFoundation\RedirectResponse;

interface LoginServiceInterface
{
    /**
     * Redirect to OAuth authorization service URL.
     *
     * @param string $provider
     * @return RedirectResponse
     */
    public function redirect(string $provider): RedirectResponse;

    /**
     * Handle callback.
     *
     * @param string $provider
     * @return User
     */
    public function callback(string $provider): User;
}
