<?php

namespace App\Http\Livewire\Contact;

use App\Interfaces\ContactServiceInterface;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Index extends Component
{
    public bool $displayQR = false;

    public function toggleQR()
    {
        $this->displayQR = false;
    }

    public function render(ContactServiceInterface $contactService)
    {
        $user = Auth::user();

        return view('livewire.contact.index', [
            'contacts' => $contactService->contacts($user),
        ]);
    }
}
