<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageDeleted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @var Message
     */
    public $message;

    /**
     * Create a new event instance.
     *
     * @param Message $message
     * @return void
     */
    public function __construct(Message $message)
    {
        $this->message = $message;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        return new PrivateChannel("thread.{$this->message->thread_id}");
    }

    /**
     * @note It's important to redo the action. It will be stored in the session, so it's temporary.
     *
     * Get the data to broadcast.
     *
     * @return array
     */
    public function broadcastWith()
    {
        return [
            'type'       => 'message',
            'id'         => (string) $this->message->id,
            'attributes' => [
                'thread_id'    => $this->message->thread_id,
                'user_id'      => $this->message->user_id,
                'body'         => $this->message->body,
                'created_at'   => $this->message->created_at,
                'updated_at'   => $this->message->updated_at,
            ],
        ];
    }
}
