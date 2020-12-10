<?php

namespace Tests\Feature;

use App\Interfaces\ContactServiceInterface;
use App\Models\Contact;
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
     * A basic feature test example.
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
     * A basic feature test example.
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
}
