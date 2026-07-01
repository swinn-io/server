<?php

namespace App\Models;

use App\Traits\HasUUID;
use Cmgmyr\Messenger\Models\Message as BaseMessage;
use Database\Factories\MessageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $thread_id
 * @property string $user_id
 * @property array<string, mixed> $body
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read User|null $user
 */
class Message extends BaseMessage
{
    /** @use HasFactory<MessageFactory> */
    use HasFactory;

    use HasUUID;

    /**
     * The attributes that should be cast to native types.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'thread_id' => 'string',
        'user_id' => 'string',
        'body' => 'array',
    ];
}
