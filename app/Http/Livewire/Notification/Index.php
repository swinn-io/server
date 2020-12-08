<?php

namespace App\Http\Livewire\Notification;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Index extends Component
{
    public function render()
    {
        return view('livewire.notification.index', [
            'notifications' => Auth::user()->unreadNotifications()->paginate(),
        ]);
    }
}
