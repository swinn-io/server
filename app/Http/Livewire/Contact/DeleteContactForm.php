<?php

namespace App\Http\Livewire\Contact;

use App\Models\Contact;
use Livewire\Component;

class DeleteContactForm extends Component
{
    public Contact $contact;

    public function mount($contact)
    {
        $this->contact = $contact;
    }

    public function confirmContactDeletion()
    {
        $this->resetErrorBag();

        $this->password = '';

        $this->dispatchBrowserEvent('confirming-delete-contact');

        $this->confirmingContactDeletion = true;
    }

    public function deleteContact(string $id)
    {
        $contact = Contact::findOrFail($id);
        $name = $contact->name;
        $contact->delete();

        session()->flash('message', __(':name is no longer your contacts.', compact('name')));

        return redirect()->to(route('contact.index'));
    }

    public function render()
    {
        return view('livewire.contact.delete-contact-form', ['contact' => $this->contact]);
    }
}
