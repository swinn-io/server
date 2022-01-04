<?php

namespace App\Models;

use App\Traits\HasUUID;
use Cmgmyr\Messenger\Models\Message;
use Cmgmyr\Messenger\Models\Models;
use Cmgmyr\Messenger\Models\Thread as BaseThread;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Thread extends BaseThread
{
    use HasFactory, SoftDeletes, HasUUID;

    /**
     * Internal cache for creator.
     *
     * @var null|Models::user()|\Illuminate\Database\Eloquent\Model
     */
    protected $creatorCache;

    /**
     * Messages relationship.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     *
     * @codeCoverageIgnore
     */
    public function messages(): HasMany
    {
        return $this
            ->hasMany(Models::classname(Message::class), 'thread_id', 'id')
            ->orderBy('created_at', 'desc');
    }
}
