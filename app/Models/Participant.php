<?php

namespace App\Models;

use App\Traits\HasUUID;
use Cmgmyr\Messenger\Models\Participant as BaseParticipant;

class Participant extends BaseParticipant
{
    use HasUUID;

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = ['last_read', 'created_at', 'updated_at', 'deleted_at'];
}
