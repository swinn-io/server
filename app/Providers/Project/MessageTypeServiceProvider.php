<?php

namespace App\Providers\Project;

use App\MessageTypes\CurrencyType;
use App\MessageTypes\FileReferenceType;
use App\MessageTypes\LocationType;
use App\MessageTypes\MetricType;
use App\MessageTypes\MoodType;
use App\MessageTypes\PingType;
use App\MessageTypes\StatusType;
use App\Services\TypeRegistry;
use Illuminate\Support\ServiceProvider;

class MessageTypeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TypeRegistry::class, function () {
            return new TypeRegistry([
                new CurrencyType,
                new LocationType,
                new StatusType,
                new FileReferenceType,
                new MetricType,
                new MoodType,
                new PingType,
            ]);
        });
    }
}
