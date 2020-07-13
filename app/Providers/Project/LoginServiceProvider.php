<?php

namespace App\Providers\Project;

use App\Interfaces\LoginServiceInterface;
use App\Services\LoginService;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\ClientRepository;

class LoginServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->bind(LoginServiceInterface::class, function ($app) {
            return new LoginService(
                $app->make(ClientRepository::class)
            );
        });
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return [LoginServiceInterface::class];
    }
}
