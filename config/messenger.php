<?php

use App\Models\Message;
use App\Models\Participant;
use App\Models\Thread;
use App\Models\User;

return [

    'user_model' => User::class,

    'message_model' => Message::class,

    'participant_model' => Participant::class,

    'thread_model' => Thread::class,

    /**
     * Define custom database table names - without prefixes.
     */
    'messages_table' => 'messages',

    'participants_table' => 'participants',

    'threads_table' => 'threads',
];
