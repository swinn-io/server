<?php

namespace App\Http\Controllers;

use App\Interfaces\LoginServiceInterface;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LoginController extends Controller
{
    /**
     * Login Service.
     */
    private LoginServiceInterface $service;

    /**
     * LoginController constructor.
     */
    public function __construct(LoginServiceInterface $service)
    {
        $this->service = $service;
    }

    /**
     * Socialite integrations provider selection to authenticate.
     *
     * @return Application|Factory|View
     */
    public function home(Request $request)
    {
        $allParams = $request->all();

        $request->session()->flash('client', $allParams);

        $params = http_build_query($allParams);

        return view('login', compact('params'));
    }

    /**
     * Redirect the user to the provider authentication page.
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function redirect(string $provider, Request $request)
    {
        try {
            $client = collect($request->session()->get('client'));
            if ($client->has('state') && $client->has('redirect_uri')) {
                $request->session()->reflash();
            } else {
                $request->session()->flash('client', $request->all());
            }

            return $this->service->redirect($provider);
        } catch (\Exception $exception) {
            abort(404);
        }
    }

    /**
     * Obtain the user information from the provider.
     *
     * @return Application|RedirectResponse|Redirector
     */
    public function callback(string $provider, Request $request)
    {
        /** @var array<string, mixed> $client */
        $client = $request->session()->get('client', []);
        $user = $this->service->callback($provider, $client);
        $URI = Arr::get($client, 'redirect_uri', false);

        /**
         * Authorize user before redirection, it's required for PKCE
         * it will also remember the client user.
         */
        Auth::login($user, true);

        $query = http_build_query([
            'user' => $user->toArray(),
            'access_token' => $this->service->createToken($user),
            'state' => Arr::get($client, 'state', null),
        ]);

        return $URI ? redirect("$URI?{$query}") : redirect('/');
    }

    /**
     * Logout current user.
     *
     * @return Application|RedirectResponse|Redirector
     */
    public function logout(Request $request)
    {
        // Revoke the user's Passport access tokens so API sessions die with the web session.
        Auth::user()?->tokens->each->revoke();

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
