<?php

namespace Tests\Feature;

use App\Interfaces\ContactServiceInterface;
use App\Models\Contact;
use App\Models\User;
use Database\Seeders\ContactSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class ContactTest extends TestCase
{
    use WithFaker;

    /**
     * @var ContactServiceInterface
     */
    private ContactServiceInterface $service;

    /**
     * Setup testing.
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ContactServiceInterface::class);
        $this->seed(UserSeeder::class);
        $this->seed(ContactSeeder::class);
    }

    /**
     * Check index method of ContactController.
     *
     * @return void
     */
    public function testContactControllerIndexMethod()
    {
        $contact = Contact::inRandomOrder()->with('user')->first();
        $contacts = $this->service->contacts($contact->user);
        $response = $this
            ->actingAs($contact->user, 'api')
            ->get(route('contact'));

        $response->assertOk();
        $response->assertJson([
            'data' => [
                0 => [
                    'type' => 'contact',
                    'attributes' => [
                        'user_id' => $contact->user->id,
                    ],
                ],
            ],
            'meta' => [
                'total' => $contacts->total(),
            ],
        ]);
    }

    /**
     * Check show method of ContactController.
     *
     * @return void
     */
    public function testContactControllerShowMethod()
    {
        $contact = Contact::inRandomOrder()->with('user')->first();
        $response = $this
            ->actingAs($contact->user, 'api')
            ->get(route('contact.show', ['id' => $contact->id]));

        $response->assertOk();
        $response->assertJson([
            'data' => [
                'type' => 'contact',
                'id' => $contact->id,
                'attributes' => [
                    'name' => $contact->name,
                    'user_id' => $contact->user->id,
                ],
            ],
        ]);
    }

    /**
     * Check show method of ContactController.
     *
     * @return void
     */
    public function testContactControllerStoreMethod()
    {
        $user = User::factory()->create();
        $contact = User::factory()->create();
        $response = $this
            ->actingAs($user, 'api')
            ->post(route('contact.store', ['user_id' => $contact->id]));

        $response->assertCreated();
        $response->assertJson([
            'data' => [
                'type' => 'contact',
                'attributes' => [
                    'name' => $contact->name,
                    'user_id' => $user->id,
                    'source_type' => get_class($contact),
                    'source_id' => $contact->id,
                ],
            ],
        ]);
    }
}
