<?php

namespace App\Http\Controllers;

use App\User;
use Laravel\Socialite\Facades\Socialite;

class LoginController extends Controller
{
    /**
     * Redirect the user to the GitHub authentication page.
     *
     * @param string $provider
     * @return mixed
     */
    public function redirect(string $provider)
    {
        try {
            return Socialite::driver($provider)
                ->scopes(config("services.{$provider}.scopes") ?? '*')
                ->stateless()
                ->redirect();
        } catch (\Exception $exception) {
            abort(404);
        }
    }

    /**
     * Obtain the user information from GitHub.
     *
     * @param string $provider
     * @return \Illuminate\Http\JsonResponse
     */
    public function callback(string $provider)
    {
        $get  = Socialite::driver($provider)->stateless()->user();
        $user = User::updateOrCreate([
            'provider_name'  => $provider,              // GitHub, LinkedIn, Google, Apple
            'provider_id'    => $get->getId(),          // uuid-0001-0002-0003
        ],[
            'name'           => $get->getName(),
            'access_token'   => $get->token,            // TOKEN
            'refresh_token'  => $get->refreshToken,     // TOKEN - some providers have it
            'profile'        => $get->user,             // JSON profile data
        ]);

        return response()->json($user);
    }
}
