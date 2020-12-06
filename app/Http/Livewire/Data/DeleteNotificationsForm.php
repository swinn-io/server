<?php

namespace App\Http\Livewire\Data;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class DeleteNotificationsForm extends Component
{
    public function deleteAllNotifications()
    {
        /**
         * @var User $user
         */
        $user = Auth::user();
        $user->notifications()->delete();
    }

    public function render()
    {
        return view('livewire.data.delete-notifications-form');
    }
}
