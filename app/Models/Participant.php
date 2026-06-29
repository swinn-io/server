<?php

namespace App\Models;

use App\Traits\HasUUID;
use Cmgmyr\Messenger\Models\Participant as BaseParticipant;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $thread_id
 * @property string $user_id
 * @property Carbon|null $last_read
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class Participant extends BaseParticipant
{
    use HasUUID;

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'last_read' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];
}
