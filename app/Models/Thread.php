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

    /**
     * Mark a thread as read for a user.
     *
     * @param int $userId
     *
     * @return Participant
     */
    public function markAsRead($userId): Participant
    {
        $participant = $this->getParticipantFromUser($userId);
        $participant->last_read = now();
        $participant->save();


        return $participant;
    }

    /**
     * Restores all participants within a thread that has a new message.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function activateAllParticipants()
    {
        $participants = $this->participants()->withTrashed()->get();
        foreach ($participants as $participant) {
            $participant->restore();
        }

        return $participants;
    }
}
