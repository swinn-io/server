<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */

use App\Models\Participant;
use App\Models\Thread;
use App\Models\User;
use Faker\Generator as Faker;

$factory->define(Participant::class, function (Faker $faker) {
    return [
        'thread_id' => Thread::inRandomOrder()->first(),
        'user_id' => User::inRandomOrder()->first(),
        'last_read' => now(),
    ];
});
