<?php

namespace App\Notifications;

use App\Http\Resources\ParticipantResource;
use App\Models\Participant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ParticipantCreated extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @var Participant
     */
    public Participant $participant;

    /**
     * Create a new notification instance.
     *
     * @param Participant $participant
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
     * @return array
     */
    public function via($notifiable)
    {
        return $notifiable->notify_via;
    }

    /**
     * Get the array representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function toArray($notifiable)
    {
        return [
            'payload' => (new ParticipantResource($this->participant))->resolve(),
        ];
    }
}
