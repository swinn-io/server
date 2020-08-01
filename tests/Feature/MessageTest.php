<?php

namespace Tests\Feature;

use App\Interfaces\MessageServiceInterface;
use App\Models\Thread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use MessagingSeeder;
use Tests\TestCase;
use UserSeeder;

class MessageTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    /**
     * @var MessageServiceInterface
     */
    private $service;

    /**
     * Setup testing.
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(MessageServiceInterface::class);
        $this->seed(UserSeeder::class);
        $this->seed(MessagingSeeder::class);

        // Behave like an API call
        $this->withHeader('Accept', 'application/json');
    }

    /**
     * Index method test without authorization.
     *
     * @return void
     */
    public function testIndexWithoutAuthorization()
    {
        $response = $this->get(route('message'));

        $response->assertUnauthorized();
    }

    /**
     * Index method test as an authorized user.
     *
     * @return void
     */
    public function testIndex()
    {
        $thread = Thread::with('participants.user')->inRandomOrder()->first();
        $user = $thread->participants->first()->user;
        $total = $this->service->threads($user->id);

        $response = $this
            ->actingAs($user, 'api')
            ->get(route('message'));

        $response
            ->assertOk()
            ->assertJson([
                'data' => [],
                'meta' => [
                    'total' => $total->total(),
                ],
            ]);
    }

    /**
     * Index method test without authorization.
     *
     * @return void
     */
    public function testStoreWithoutAuthorization()
    {
        $response = $this->post(route('message.store'));

        $response->assertUnauthorized();
    }

    /**
     * Index method test as an authorized user.
     *
     * @return void
     */
    public function testStore()
    {
        $user = factory(User::class)->create();
        $thread = factory(Thread::class)->make();
        $content = ['some' => 'data'];

        // Test with invalid data and receive 422

        $response = $this
            ->actingAs($user, 'api')
            ->post(route('message'));

        $response
            ->assertStatus(422)
            ->assertJson([
                'message' => 'The given data was invalid.',
                'errors' => [
                    'subject' => [],
                    'content' => [],
                ],
            ]);

        // Make request correctly

        $response = $this
            ->actingAs($user, 'api')
            ->post(route('message'), [
                'subject' => $thread->subject,
                'content' => $content,
            ]);

        $response
            ->assertCreated()
            ->assertJson([
                'data' => [
                    'type' => 'tread',
                    'attributes' => [
                        'subject'  => $thread->subject,
                        'messages' => [
                            [
                                'type' => 'message',
                                'attributes' => [
                                    'user_id' => $user->id,
                                    'body' => $content,
                                ],
                            ],
                        ],
                        'participants' => [
                            [
                                'type' => 'participant',
                                'attributes' => [
                                    'user_id' => $user->id,
                                ],
                            ],
                        ],
                    ],
                ],
            ]);
    }
}
