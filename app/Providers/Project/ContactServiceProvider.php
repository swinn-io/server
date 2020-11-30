<?php

namespace App\Providers\Project;

use App\Interfaces\ContactServiceInterface;
use App\Services\ContactService;
use Illuminate\Support\ServiceProvider;

class ContactServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->bind(ContactServiceInterface::class, function ($app) {
            return new ContactService();
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
}
