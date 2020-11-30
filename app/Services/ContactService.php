<?php

namespace App\Services;

use App\Interfaces\ContactServiceInterface;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ContactService implements ContactServiceInterface
{
    /**
     * All contacts.
     *
     * @param User $user
     * @return LengthAwarePaginator
     */
    public function contacts(User $user): LengthAwarePaginator
    {
        return Contact::forUser($user->id)->paginate();
    }

    /**
     * Retrieve a contact.
     *
     * @param string $contact_id
     * @return Contact
     */
    public function contact(string $contact_id): Contact
    {
        return Contact::find($contact_id);
    }

    /**
     * Creates contact.
     * @param User $user
     * @param User $contact
     * @return Contact
     */
    public function addContact(User $user, User $contact): Contact
    {
        return Contact::updateOrCreate([
            'user_id' => $user->id,
            'source_type' => get_class($contact),
            'source_id' => $contact->id,
        ], [
            'name' => $contact->name,
        ]);
    }

    /**
     * Remove a contact.
     *
     * @param string $contact_id
     * @return Contact
     */
    public function removeContact(string $contact_id): Contact
    {
        $contact = Contact::find($contact_id);
        $contact->delete();

        return $contact;
    }
}
