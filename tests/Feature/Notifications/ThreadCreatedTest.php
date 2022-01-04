<?php

namespace Tests\Feature\Notifications;

use App\Interfaces\ContactServiceInterface;
use App\Interfaces\MessageServiceInterface;
use App\Models\Message;
use App\Models\Thread;
use App\Models\User;
use App\Notifications\ThreadCreated;
use Database\Seeders\MessagingSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ThreadCreatedTest extends TestCase
{

    /**
     * @var MessageServiceInterface
     */
    private MessageServiceInterface $service;

    /**
     * @var ContactServiceInterface
     */
    private ContactServiceInterface $contactService;

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
     * A basic feature test example.
     *
     * @return void
     */
    public function testExample()
    {
        Notification::fake();

        $thread = Thread::factory()->make();
        $message = Message::factory()->make();
        $users = User::factory(4)->create();
        $user = $users->first();

        $response = $this
            ->actingAs($user, 'api')
            ->postJson(route('message.store'), [
                'subject' => $thread->subject,
                'content' => $message->body,
                'recipients' => $users->pluck('id')->toArray(),
            ]);

        $response->assertCreated();
        Notification::assertSentTo(
            $users,
            ThreadCreated::class,
            function ($notification, $channels) use ($thread) {
                return $notification->thread->id === $thread->id;
            }
        );
    }
}
