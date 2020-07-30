<?php

namespace App\Models;

use App\Traits\HasUUID;
use Cmgmyr\Messenger\Models\Message as BaseMessage;

class Message extends BaseMessage
{
    use HasUUID;

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'thread_id' => 'string',
        'user_id' => 'string',
        'body' => 'array',
    ];
}
