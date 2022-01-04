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
     *
     * @param  User  $user
     * @return LengthAwarePaginator
     */
    public function contacts(User $user): LengthAwarePaginator;

    /**
     * Retrieve a contact.
     *
     * @param  string  $contact_id
     * @param  User  $user
     * @return Contact
     */
    public function contact(string $contact_id, User $user): ?Contact;

    /**
     * Creates contact.
     *
     * @param  User  $user
     * @param  User  $contact
     * @return Contact
     */
    public function addContact(User $user, User $contact): Contact;

    /**
     * Creates contact by user collection and returns contact.
     *
     * @param  Collection  $users
     * @return Collection
     */
    public function setContacts(Collection $users): Collection;

    /**
     * Remove a contact.
     *
     * @param  string  $contact_id
     * @return Contact
     */
    public function removeContact(string $contact_id): Contact;
}
