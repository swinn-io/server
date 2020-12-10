<?php

namespace Tests\Feature;

use App\Interfaces\ContactServiceInterface;
use App\Interfaces\MessageServiceInterface;
use App\Models\Message;
use App\Models\Thread;
use App\Models\User;
use Database\Seeders\MessagingSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class MessageTest extends TestCase
{
    use WithFaker;

    /**
     * @var MessageServiceInterface
     */
    private MessageServiceInterface $service;

    /**
     * @var ContactServiceInterface
     */
    private ContactServiceInterface $contactService;

    /**
    put('{id}', ['as' => 'message.update', 'uses' => 'MessageController@update']);
    post('{id}', ['as' => 'message.new', 'uses' => 'MessageController@new']);
     */

    /**
     * Setup testing.
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(MessageServiceInterface::class);
        $this->contactService = app(ContactServiceInterface::class);
        $this->seed(UserSeeder::class);
        $this->seed(MessagingSeeder::class);
    }

    /**
     * Check index method of MessageController.
     *
     * @return void
     */
    public function testMessageControllerIndexMethod()
    {
        $message = Message::inRandomOrder()->first();
        $user = $message->user;
        $messages = $this->service->threads($user);
        $response = $this
            ->actingAs($user, 'api')
            ->get(route('message'));

        $response->assertOk();
        $response->assertJson([
            'meta' => [
                'total' => $messages->total()
            ]
        ]);
    }

    /**
     * Check store method of MessageController.
     *
     * @return void
     */
    public function testMessageControllerStoreMethod()
    {
        $thread = Thread::factory()->make();
        $message = Message::factory()->make();
        $users = User::factory(4)->create();
        $user = $users->pop();
        $response = $this
            ->actingAs($user, 'api')
            ->postJson(route('message.store'), [
                'subject' => $thread->subject,
                'content' => $message->body,
                'recipients' => $users->pluck('id')->toArray(),
            ]);

        $response->assertCreated();
        $response->assertJson([
            'data' => [
                'type' => 'thread',
                'attributes' => [
                    'subject' => $thread->subject,
                    'messages' => [
                        [
                            'type' => 'message',
                            'attributes' => [
                                'user_id' => $user->id,
                                'body' => $message->body,
                            ]
                        ]
                    ]
                ]
            ]
        ]);
        $response->assertJsonCount(1, 'data.attributes.messages');
        $response->assertJsonCount(4, 'data.attributes.participants');
    }

    /**
     * Check show method of MessageController.
     *
     * @return void
     */
    public function testMessageControllerShowMethod()
    {
        $thread = Thread::inRandomOrder()->first();
        $user = $thread->creator();
        $messagesCount = $thread->messages()->count();
        $participantsCount = $thread->participants()->count();
        $response = $this
            ->actingAs($user, 'api')
            ->get(route('message.show', ['id' => $thread->id]));

        $response->assertOk();
        $response->assertJson([
            'data' => [
                'type' => 'thread',
                'id' => $thread->id,
                'attributes' => [
                    'subject' => $thread->subject
                ]
            ]
        ]);
        $response->assertJsonCount($messagesCount, 'data.attributes.messages');
        $response->assertJsonCount($participantsCount, 'data.attributes.participants');
    }

    /**
     * Check new method of MessageController.
     *
     * @return void
     */
    public function testMessageControllerNewMethod()
    {
        $thread = Thread::inRandomOrder()->first();
        $user = $thread->creator();
        $messagesCount = $thread->messages()->count();
        $content = ['test' => 'data'];
        $response = $this
            ->actingAs($user, 'api')
            ->postJson(route('message.new', ['id' => $thread->id]), [
                'body' => $content,
            ]);

        $response->assertCreated();
        $response->assertJson([
            'data' => [
                'type' => 'message',
                'attributes' => [
                    'thread_id' => $thread->id,
                    'user_id' => $user->id,
                    'body' => $content,
                ]
            ]
        ]);

        $response = $this
            ->actingAs($user, 'api')
            ->get(route('message.show', ['id' => $thread->id]));

        $response->assertOk();
        $response->assertJsonCount(($messagesCount + 1), 'data.attributes.messages');
    }
}
