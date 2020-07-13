<?php

namespace App\Http\Controllers;

use App\Interfaces\LoginServiceInterface;

class LoginController extends Controller
{
    /**
     * Login Service.
     *
     * @var LoginServiceInterface $service
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
     * Redirect the user to the GitHub authentication page.
     *
     * @param string $provider
     * @return mixed
     */
    public function redirect(string $provider)
    {
        return $this->service->redirect($provider);
    }

    /**
     * Obtain the user information from GitHub.
     *
     * @param string $provider
     * @return \Illuminate\Http\JsonResponse
     */
    public function callback(string $provider)
    {
        $callback = $this->service->callback($provider);

        return response()->json($callback);
    }
}
