<?php

namespace App\Http\Livewire\Profile;

use App\Interfaces\MessageServiceInterface;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class PushNotificationForm extends Component
{
    public function pushNotification(MessageServiceInterface $messageService)
    {
        $subject = 'PING '.now();
        $messageService->newThread($subject, Auth::user(), ['DUMMY PING']);
    }

    public function render()
    {
        return view('livewire.profile.push-notification-form');
    }
}
