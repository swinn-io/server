<?php

namespace App\Models;

use App\Traits\HasUUID;
use Cmgmyr\Messenger\Models\Participant as BaseParticipant;

class Participant extends BaseParticipant
{
    use HasUUID;
}
