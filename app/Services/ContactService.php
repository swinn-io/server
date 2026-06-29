<?php

namespace App\Services;

use App\Interfaces\ContactServiceInterface;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class ContactService implements ContactServiceInterface
{
    /**
     * All contacts.
     */
    public function contacts(User $user): LengthAwarePaginator
    {
        return Contact::forUser($user->id)->with('source')->paginate();
    }

    /**
     * Retrieve a contact.
     */
    public function contact(string $contact_id, User $user): ?Contact
    {
        return Contact::with('user')->where('id', $contact_id)->forUser($user->id)->first();
    }

    /**
     * Creates contact.
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
     * Creates contact by user collection and returns contact.
     */
    public function setContacts(Collection $users): Collection
    {
        return $users->map(function ($user) use ($users) {
            return $users
                ->filter(function ($item) use ($user) {
                    return ! $item->is($user);
                })
                ->map(function ($contact) use ($user) {
                    return $this->addContact($user, $contact);
                });
        })
            ->flatten();
    }

    /**
     * Remove a contact.
     */
    public function removeContact(string $contact_id): Contact
    {
        $contact = Contact::find($contact_id);
        $contact->delete();

        return $contact;
    }
}
