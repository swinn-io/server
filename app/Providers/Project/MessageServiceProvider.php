<?php

namespace App\Providers\Project;

use App\Interfaces\ContactServiceInterface;
use App\Interfaces\MessageServiceInterface;
use App\Services\MessageService;
use Illuminate\Support\ServiceProvider;

class MessageServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->bind(MessageServiceInterface::class, function ($app) {
            return new MessageService(
                $this->app->make(ContactServiceInterface::class)
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
}
