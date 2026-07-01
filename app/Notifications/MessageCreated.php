<?php

namespace App\Notifications;

use App\Http\Resources\MessageResource;
use App\Models\Message;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MessageCreated extends Notification implements ShouldQueue
{
    use Queueable;

    public Message $message;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct(Message $message)
    {
        $this->message = $message;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  User  $notifiable
     * @return array<int, string>
     */
    public function via($notifiable)
    {
        return $notifiable->notify_via;
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
            'payload' => (new MessageResource($this->message))->resolve(),
        ];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return MailMessage
     */
    public function toMail($notifiable)
    {
        $body = json_encode($this->message->body);
        $user = $this->message->user;

        return (new MailMessage)
            ->subject('New Message from: '.$user?->name)
            ->greeting('Hello!')
            ->line("{$user?->name} pinged you!")
            ->line("{$body}")
            ->line('Thank you for using our application!');
    }
}
