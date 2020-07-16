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
     * Redirect the user to the provider authentication page.
     *
     * @param string $provider
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function redirect(string $provider, Request $request)
    {
        try {
            $request->session()->flash('client', $request->all());

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
            $client = $request->session()->get('client');
            $callback = $this->service->callback($provider, $client);
            $URI = Arr::get($client, 'redirect_uri');
            $query = http_build_query([
                'state'    => Arr::get($client, 'state'),
                'callback' => $callback->toArray(),
            ]);

            return redirect()->away("$URI?{$query}");
        } catch (\Exception $exception) {
            abort(404);
        }
    }
}
