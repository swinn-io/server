<?php

namespace Tests\Feature;

use App\Interfaces\MessageServiceInterface;
use App\Models\Message;
use App\Models\Thread;
use App\Models\User;
use Database\Seeders\MessagingSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class MessageTest extends TestCase
{
    use WithFaker;

    private MessageServiceInterface $service;

    /**
     * Setup testing.
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(MessageServiceInterface::class);
        $this->seed(UserSeeder::class);
        $this->seed(MessagingSeeder::class);
    }

    /**
     * Check index method of MessageController.
     *
     * @return void
     */
    public function test_message_controller_index_method()
    {
        $message = Message::inRandomOrder()->firstOrFail();
        $user = User::findOrFail($message->user_id);
        $messages = $this->service->threads($user);
        $response = $this
            ->actingAs($user, 'api')
            ->get(route('message'));

        $response->assertOk();
        $response->assertJson([
            'meta' => [
                'total' => $messages->total(),
            ],
        ]);
    }

    /**
     * The index endpoint must return each thread's full message history, not
     * just a preview — MessageService::threads() is shared with the
     * dashboard, which only wants a single-message preview, but this API
     * endpoint (consumed outside the web dashboard) must keep receiving the
     * complete list it always has.
     *
     * @return void
     */
    public function test_message_controller_index_method_returns_full_message_history()
    {
        $sender = User::factory()->create(['notify_via' => []]);
        $recipient = User::factory()->create(['notify_via' => []]);

        $thread = $this->service->newThread(
            'Full history',
            $sender,
            ['type' => 'mood', 'payload' => ['mood' => 'happy', 'intensity' => 1], 'version' => '1.0'],
            [$recipient->id]
        );
        $this->service->newMessage($thread, $sender, ['type' => 'mood', 'payload' => ['mood' => 'happy', 'intensity' => 2], 'version' => '1.0']);
        $this->service->newMessage($thread, $sender, ['type' => 'mood', 'payload' => ['mood' => 'happy', 'intensity' => 3], 'version' => '1.0']);

        $response = $this
            ->actingAs($sender, 'api')
            ->get(route('message'));

        $response->assertOk();
        $response->assertJson([
            'data' => [
                [
                    'id' => $thread->id,
                ],
            ],
        ]);
        $response->assertJsonCount(3, 'data.0.attributes.messages');
    }

    /**
     * Check store method of MessageController.
     *
     * @return void
     */
    public function test_message_controller_store_method()
    {
        $thread = Thread::factory()->make();
        $message = Message::factory()->make();
        $users = User::factory(4)->create();
        /** @var User $user */
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
                            ],
                        ],
                    ],
                ],
            ],
        ]);
        $response->assertJsonCount(1, 'data.attributes.messages');
        $response->assertJsonCount(4, 'data.attributes.participants');
    }

    /**
     * Check show method of MessageController.
     *
     * @return void
     */
    public function test_message_controller_show_method()
    {
        $thread = Thread::inRandomOrder()->firstOrFail();
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
                    'subject' => $thread->subject,
                ],
            ],
        ]);
        $response->assertJsonCount($messagesCount, 'data.attributes.messages');
        $response->assertJsonCount($participantsCount, 'data.attributes.participants');
    }

    /**
     * Check new method of MessageController.
     *
     * @return void
     */
    public function test_message_controller_new_method()
    {
        $thread = Thread::inRandomOrder()->firstOrFail();
        $user = $thread->creator();
        $messagesCount = $thread->messages()->count();
        $content = ['type' => 'mood', 'version' => '1.0', 'payload' => ['mood' => 'happy']];
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
                ],
            ],
        ]);

        $response = $this
            ->actingAs($user, 'api')
            ->get(route('message.show', ['id' => $thread->id]));

        $response->assertOk();
        $response->assertJsonCount(($messagesCount + 1), 'data.attributes.messages');
    }
}
