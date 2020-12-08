<?php

namespace App\Http\Livewire;

use Livewire\Component;

class NotificationListener extends Component
{
    public bool $showNewNotificationBadge = false;

    public int $count = 88;

    public $user;

    // Computed Property
    public function getCountProperty()
    {
        dump('getCountProperty()');
        return 999; //$this->user->unreadNotifications()->count();
    }

    public function mount($user)
    {
        dump('mount:$user');
        $this->user = $user;
    }

    public function getListeners()
    {
        return [ // @todo {$this->user->id}
            "echo-private:App.Models.User.3dd6a60f-8c5d-49f8-b723-514ba4a941d6,BroadcastNotificationCreated" => 'showNewNotificationBadge',
        ];
    }

    public function showNewNotificationBadge()
    {
        dump('showNewNotificationBadge()');
        $this->showNewNotificationBadge = true;
        $this->count = 999; //$this->user->unreadNotifications()->count();
    }

    public function render()
    {
        return view('livewire.notification-listener');
    }
}
