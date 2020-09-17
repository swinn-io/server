<?php

namespace App\Observers;

use App\Events\MessageCreated as MessageCreatedEvent;
use App\Events\MessageDeleted as MessageDeletedEvent;
use App\Models\Message;

class MessageObserver
{
    /**
     * Handle the message "created" event.
     *
     * @param  Message  $message
     * @return void
     */
    public function created(Message $message)
    {
        broadcast(new MessageCreatedEvent($message));
    }

    /**
     * Handle the message "deleted" event.
     *
     * @param  Message  $message
     * @return void
     */
    public function deleted(Message $message)
    {
        broadcast(new MessageDeletedEvent($message));
    }
}
