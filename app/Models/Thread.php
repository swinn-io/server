<?php

namespace App\Models;

use App\Traits\HasUUID;
use Cmgmyr\Messenger\Models\Message;
use Cmgmyr\Messenger\Models\Models;
use Cmgmyr\Messenger\Models\Thread as BaseThread;
use Database\Factories\ThreadFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $subject
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class Thread extends BaseThread
{
    /** @use HasFactory<ThreadFactory> */
    use HasFactory;

    use HasUUID, SoftDeletes;

    /**
     * Messages relationship.
     *
     * @return HasMany<\App\Models\Message, $this>
     *
     * @codeCoverageIgnore
     */
    public function messages(): HasMany
    {
        /** @var class-string<\App\Models\Message> $messageClass */
        $messageClass = Models::classname(Message::class);

        return $this
            ->hasMany($messageClass, 'thread_id', 'id')
            ->orderBy('created_at', 'desc');
    }
}
