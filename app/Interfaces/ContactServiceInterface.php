<?php

namespace App\Interfaces;

use App\Models\Contact;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface ContactServiceInterface
{
    /**
     * All contacts.
     */
    public function contacts(User $user): LengthAwarePaginator;

    /**
     * Retrieve a contact.
     */
    public function contact(string $contact_id, User $user): ?Contact;

    /**
     * Creates contact.
     */
    public function addContact(User $user, User $contact): Contact;

    /**
     * Creates contact by user collection and returns contact.
     */
    public function setContacts(Collection $users): Collection;

    /**
     * Remove a contact.
     */
    public function removeContact(string $contact_id): Contact;
}
