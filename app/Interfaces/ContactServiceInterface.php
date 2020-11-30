<?php

namespace App\Interfaces;

use App\Models\Contact;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ContactServiceInterface
{
    /**
     * All contacts.
     *
     * @param User $user
     * @return LengthAwarePaginator
     */
    public function contacts(User $user): LengthAwarePaginator;

    /**
     * Retrieve a contact.
     *
     * @param string $contact_id
     * @return Contact
     */
    public function contact(string $contact_id): Contact;

    /**
     * Creates contact.
     * @param User $user
     * @param User $contact
     * @return Contact
     */
    public function addContact(User $user, User $contact): Contact;

    /**
     * Remove a contact.
     *
     * @param string $contact_id
     * @return Contact
     */
    public function removeContact(string $contact_id): Contact;
}
