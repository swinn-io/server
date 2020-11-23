<?php

namespace App\Http\Controllers;

use App\Interfaces\LoginServiceInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    /**
     * Login Service.
     *
     * @var LoginServiceInterface
     */
    private LoginServiceInterface $service;

    /**
     * LoginController constructor.
     *
     * @param LoginServiceInterface $service
     */
    public function __construct(LoginServiceInterface $service)
    {
        $this->service = $service;
    }

    /**
     * Socialite integrations provider selection to authenticate.
     *
     * @param Request $request
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function home(Request $request)
    {
        $allParams = $request->all();
        $request->session()->flash('client', $allParams);

        $params = http_build_query($allParams);

        return view('auth.login', compact('params'));
    }

    /**
     * Redirect the user to the provider authentication page.
     *
     * @param string $provider
     * @param Request $request
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
     * @param string $provider
     * @param Request $request
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function callback(string $provider, Request $request)
    {
        $client = $request->session()->get('client', []);
        $user = $this->service->callback($provider, $client);
        $URI = Arr::get($client, 'redirect_uri', false);

        /**
         * Authorize user before redirection, it's required for PKCE
         * it will also remember the client user
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
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function logout()
    {
        Auth::logout();

        return redirect('/');
    }
}
