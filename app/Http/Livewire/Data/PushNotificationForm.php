<?php

namespace App\Http\Livewire\Data;

use App\Interfaces\MessageServiceInterface;
use App\Models\Message;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class PushNotificationForm extends Component
{
    public function pushNotification(MessageServiceInterface $messageService)
    {
        $subject = 'PING '.now();
        $message = Message::factory()->make();
        $messageService->newThread($subject, Auth::user(), $message->body);
    }

    public function render()
    {
        return view('livewire.data.push-notification-form');
    }
}
