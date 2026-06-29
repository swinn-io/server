<?php

namespace App\Notifications;

use App\Http\Resources\ParticipantResource;
use App\Models\Participant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class ParticipantCreated extends Notification implements ShouldQueue
{
    use Queueable;

    public Participant $participant;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct(Participant $participant)
    {
        $this->participant = $participant;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array<int, string>
     */
    public function via($notifiable)
    {
        /** @var array<int, string> $channels */
        $channels = $notifiable->notify_via;

        return $channels;
    }

    /**
     * Get the array representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return array<string, mixed>
     */
    public function toArray($notifiable)
    {
        return [
            'payload' => (new ParticipantResource($this->participant))->resolve(),
        ];
    }
}
