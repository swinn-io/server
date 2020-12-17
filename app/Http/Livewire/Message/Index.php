<?php

namespace App\Http\Livewire\Message;

use App\Interfaces\MessageServiceInterface;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Index extends Component
{
    private MessageServiceInterface $service;

    public function mount(MessageServiceInterface $service)
    {
        $this->service = $service;
    }

    public function render()
    {
        $threads = $this->service->threads(Auth::user());
        return view('livewire.message.index', ['threads' => $threads]);
    }
}
