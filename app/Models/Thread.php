<?php

namespace App\Models;

use App\Traits\HasUUID;
use Cmgmyr\Messenger\Models\Message;
use Cmgmyr\Messenger\Models\Models;
use Cmgmyr\Messenger\Models\Thread as BaseThread;

class Thread extends BaseThread
{
    use HasUUID;

    /**
     * Messages relationship.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     *
     * @codeCoverageIgnore
     */
    public function messages()
    {
        return $this
            ->hasMany(Models::classname(Message::class), 'thread_id', 'id')
            ->orderBy('created_at', 'desc');
    }
}
