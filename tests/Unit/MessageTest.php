<?php

namespace Tests\Unit;

use App\Interfaces\MessageServiceInterface;
use App\Models\Thread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Tests\Traits\WithAccessToken;

class MessageTest extends TestCase
{
    use WithAccessToken, WithFaker;

    /**
     * Access token.
     *
     * @var User
     */
    protected $user;

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
        $this->user = factory(User::class)->create();

        $this->prepareAccessTokenForRequest($this->user);

        $this->service->newThread('Sample Thread', $this->user->id, ['data' => 'sample']);
    }

    /**
     * Index method test as an authorized user.
     *
     * @return void
     */
    public function testIndex()
    {
        $total = $this->service->threads($this->user->id);
        $response = $this->get(route('message'));

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
     * Index method test as an authorized user.
     *
     * @return void
     */
    public function testStore()
    {
        $thread = factory(Thread::class)->make();
        $content = ['some' => 'data'];

        // Test with invalid data and receive 422

        $response = $this
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
                                    'user_id' => $this->user->id,
                                    'body' => $content,
                                ],
                            ],
                        ],
                        'participants' => [
                            [
                                'type' => 'participant',
                                'attributes' => [
                                    'user_id' => $this->user->id,
                                ],
                            ],
                        ],
                    ],
                ],
            ]);
    }
}
