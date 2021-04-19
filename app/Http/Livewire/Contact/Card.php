<?php

namespace App\Http\Livewire\Contact;

use App\Models\Contact;
use Livewire\Component;

class Card extends Component
{
    public Contact $contact;

    public function mount($contact)
    {
        $this->contact = $contact;
    }

    public function render()
    {
        return view('livewire.contact.card', ['contact' => $this->contact]);
    }
}
