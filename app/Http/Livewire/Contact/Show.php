<?php

namespace App\Http\Livewire\Contact;

use App\Interfaces\ContactServiceInterface;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Show extends Component
{
    public User $user;
    public Contact $contact;

    public function mount(string $id, ContactServiceInterface $contactService)
    {
        $this->user = Auth::user();
        $this->contact = $contactService->contact($id, $this->user) ?? abort(404);
    }

    public function render()
    {
        return view('livewire.contact.show', [
            'contact' => $this->contact,
        ]);
    }
}
