<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */

use App\Models\Message;
use App\Models\Thread;
use App\Models\User;
use Faker\Generator as Faker;

$factory->define(Message::class, function (Faker $faker) {
    return [
        'thread_id' => Thread::inRandomOrder()->first()->id,
        'user_id'   => User::inRandomOrder()->first()->id,
        'body'      => ['JSON' => 'Data'],
    ];
});
