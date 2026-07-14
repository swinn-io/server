<?php

namespace Tests\Feature;

use App\Interfaces\MessageServiceInterface;
use App\Models\Message;
use App\Models\Thread;
use App\Models\User;
use App\Notifications\MessageCreated;
use App\Notifications\ThreadCreated;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PingThreadUsersCommandTest extends TestCase
{
    private MessageServiceInterface $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(MessageServiceInterface::class);
    }

    /**
     * A valid typed envelope for seeding threads/messages in tests.
     *
     * @return array<string, mixed>
     */
    private function envelope(string $mood = 'happy'): array
    {
        return ['type' => 'mood', 'version' => '1.0', 'payload' => ['mood' => $mood]];
    }

    private function pingMessage(Thread $thread): ?Message
    {
        return $thread->messages()->get()
            ->first(fn (Message $m) => is_array($m->body) && ($m->body['type'] ?? null) === 'ping');
    }

    public function test_pings_selected_users_in_an_existing_thread(): void
    {
        Notification::fake();

        $sender = User::factory()->create(['notify_via' => ['broadcast']]);
        $recipient = User::factory()->create(['notify_via' => ['broadcast']]);
        $other = User::factory()->create(['notify_via' => ['broadcast']]);

        $thread = $this->service->newThread('Subject', $sender, $this->envelope(), [$recipient->id, $other->id]);

        $this->artisan('thread:ping', [
            '--thread' => $thread->id,
            '--from' => $sender->id,
            '--user' => [$recipient->id],
        ])->assertSuccessful();

        $ping = $this->pingMessage($thread);
        $this->assertNotNull($ping);
        $this->assertSame([$recipient->id], $ping->body['payload']['user_ids']);

        // It is a normal thread message: every participant is notified.
        Notification::assertSentTo($other, MessageCreated::class);
    }

    public function test_creates_a_new_thread_and_pings_recipients(): void
    {
        Notification::fake();

        $sender = User::factory()->create(['notify_via' => ['broadcast']]);
        $a = User::factory()->create(['notify_via' => ['broadcast']]);
        $b = User::factory()->create(['notify_via' => ['broadcast']]);

        $this->artisan('thread:ping', [
            '--from' => $sender->id,
            '--subject' => 'Heads up',
            '--user' => [$a->id, $b->id],
            '--note' => 'please respond',
        ])->assertSuccessful();

        $thread = Thread::where('subject', 'Heads up')->firstOrFail();

        $participantIds = $thread->users()->get()->pluck('id')->all();
        $this->assertContains($a->id, $participantIds);
        $this->assertContains($b->id, $participantIds);
        $this->assertContains($sender->id, $participantIds);

        $ping = $this->pingMessage($thread);
        $this->assertNotNull($ping);
        $this->assertSame([$a->id, $b->id], $ping->body['payload']['user_ids']);
        $this->assertSame('please respond', $ping->body['payload']['note']);

        Notification::assertSentTo($a, ThreadCreated::class);
    }

    public function test_fails_gracefully_when_note_exceeds_max_length(): void
    {
        Notification::fake();

        $sender = User::factory()->create(['notify_via' => ['broadcast']]);
        $recipient = User::factory()->create(['notify_via' => ['broadcast']]);
        $thread = $this->service->newThread('Subject', $sender, $this->envelope(), [$recipient->id]);

        $this->artisan('thread:ping', [
            '--thread' => $thread->id,
            '--from' => $sender->id,
            '--user' => [$recipient->id],
            '--note' => str_repeat('a', 281),
        ])->assertFailed();

        $this->assertNull($this->pingMessage($thread));
    }
}
