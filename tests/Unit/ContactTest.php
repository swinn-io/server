<?php

namespace Tests\Unit;

use App\Interfaces\ContactServiceInterface;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Arr;
use Tests\TestCase;

class ContactTest extends TestCase
{
    use WithFaker;

    /**
     * @var ContactServiceInterface
     */
    private $service;

    /**
     * Setup testing.
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ContactServiceInterface::class);
    }

    /**
     * Check if contacts method paginates contacts of a user.
     *
     * @return void
     */
    public function testServiceMethodContacts()
    {
        $user = User::factory()->create();
        $create = 5;
        // Create 5 contacts for created user.
        User::factory($create)->create()->each(function ($item) use ($user) {
            $this->service->addContact($user, $item);
        });

        $allContacts = $this->service->contacts($user);
        $modelName = get_class(Arr::get($allContacts->items(), 0));

        $this->assertEquals($create, $allContacts->total());
        $this->assertEquals(Contact::class, $modelName);
    }

    /**
     * Check if can create contact.
     *
     * @return void
     */
    public function testServiceMethodAddContact()
    {
        $user = User::factory()->create();
        $contact = User::factory()->create();
        $created = $this->service->addContact($user, $contact);

        $this->assertEquals($created->user_id, $user->id);
        $this->assertEquals($created->source_id, $contact->id);
        $this->assertEquals(User::class, $created->source_type);
    }

    /**
     * Check if contact method returns Contact model by id.
     *
     * @return void
     */
    public function testServiceMethodContact()
    {
        $user = User::factory()->create();
        $contact = User::factory()->create();
        $created = $this->service->addContact($user, $contact);
        $find = $this->service->contact($created->id);

        $this->assertEquals($find->user_id, $user->id);
        $this->assertEquals($find->source_id, $contact->id);
        $this->assertEquals(User::class, $find->source_type);
    }

    /**
     * Check if a set of users can be contacts.
     *
     * @return void
     */
    public function testServiceMethodSetContact()
    {
        $userNumber = 5;
        $users = User::factory($userNumber)->create();
        $contacts = $this->service->setContacts($users);

        $this->assertEquals(($userNumber * ($userNumber - 1)), $contacts->count());
    }

    /**
     * Check if can remove a Contact model.
     *
     * @return void
     */
    public function testServiceMethodRemoveContact()
    {
        $user = User::factory()->create();
        $contact = User::factory()->create();
        Contact::factory(10)->create([
            'user_id' => $user->id,
        ]);
        $created = $this->service->addContact($user, $contact);
        $allContacts = $this->service->contacts($user);

        $this->assertEquals($allContacts->total(), 11);

        $remove = $this->service->removeContact($created->id);
        $allContacts = $this->service->contacts($user);

        $this->assertEquals($allContacts->total(), 10);
        $this->assertEquals($remove->user_id, $user->id);
        $this->assertEquals($remove->source_id, $contact->id);
        $this->assertEquals(User::class, $remove->source_type);
    }
}
