<?php

namespace App\Http\Controllers;

use App\Interfaces\LoginServiceInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class LoginController extends Controller
{
    /**
     * Login Service.
     *
     * @var LoginServiceInterface
     */
    private $service;

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
        $request->session()->flash('client', $request->all());

        return view('login');
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
            if($client->has('state') && $client->has('redirect_uri'))
            {
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
     * @return \Illuminate\Http\RedirectResponse
     */
    public function callback(string $provider, Request $request)
    {
        try {
            $client = $request->session()->get('client', []);
            $callback = $this->service->callback($provider, $client);
            $URI = Arr::get($client, 'redirect_uri') ?? config('app.uri');
            $query = http_build_query([
                'state'    => Arr::get($client, 'state', null),
                'callback' => $callback->toArray(),
            ]);

            return redirect()->away("$URI?{$query}");
        } catch (\Exception $exception) {
            abort(404);
        }
    }
}
