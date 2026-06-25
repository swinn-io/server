<?php

namespace App\Models;

use App\Traits\HasUUID;
use Cmgmyr\Messenger\Models\Participant as BaseParticipant;

class Participant extends BaseParticipant
{
    use HasUUID;

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'last_read' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];
}
