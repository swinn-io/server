<?php

namespace Tests\Feature;

use App\Interfaces\MessageServiceInterface;
use App\Models\Message;
use App\Models\Thread;
use App\Models\User;
use App\Notifications\MessageCreated;
use App\Notifications\ThreadCreated;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Testing\PendingCommand;
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
            ->first(fn (Message $m) => Arr::get($m->body, 'type') === 'ping');
    }

    /**
     * Run the thread:ping command and return the (narrowed) pending command
     * so assertions like assertSuccessful()/assertFailed() can be chained.
     *
     * @param  array<string, mixed>  $arguments
     */
    private function runPing(array $arguments): PendingCommand
    {
        $command = $this->artisan('thread:ping', $arguments);
        $this->assertInstanceOf(PendingCommand::class, $command);

        return $command;
    }

    public function test_pings_selected_users_in_an_existing_thread(): void
    {
        Notification::fake();

        $sender = User::factory()->create(['notify_via' => ['broadcast']]);
        $recipient = User::factory()->create(['notify_via' => ['broadcast']]);
        $other = User::factory()->create(['notify_via' => ['broadcast']]);

        $thread = $this->service->newThread('Subject', $sender, $this->envelope(), [$recipient->id, $other->id]);

        $this->runPing([
            '--thread' => $thread->id,
            '--from' => $sender->id,
            '--user' => [$recipient->id],
        ])->assertSuccessful();

        $ping = $this->pingMessage($thread);
        $this->assertNotNull($ping);
        $this->assertSame([$recipient->id], Arr::get($ping->body, 'payload.user_ids'));

        // It is a normal thread message: every participant is notified.
        Notification::assertSentTo($other, MessageCreated::class);
    }

    public function test_creates_a_new_thread_and_pings_recipients(): void
    {
        Notification::fake();

        $sender = User::factory()->create(['notify_via' => ['broadcast']]);
        $a = User::factory()->create(['notify_via' => ['broadcast']]);
        $b = User::factory()->create(['notify_via' => ['broadcast']]);

        $this->runPing([
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
        $this->assertSame([$a->id, $b->id], Arr::get($ping->body, 'payload.user_ids'));
        $this->assertSame('please respond', Arr::get($ping->body, 'payload.note'));

        Notification::assertSentTo($a, ThreadCreated::class);
    }

    public function test_fails_gracefully_when_note_exceeds_max_length(): void
    {
        Notification::fake();

        $sender = User::factory()->create(['notify_via' => ['broadcast']]);
        $recipient = User::factory()->create(['notify_via' => ['broadcast']]);
        $thread = $this->service->newThread('Subject', $sender, $this->envelope(), [$recipient->id]);

        $this->runPing([
            '--thread' => $thread->id,
            '--from' => $sender->id,
            '--user' => [$recipient->id],
            '--note' => str_repeat('a', 281),
        ])->assertFailed();

        $this->assertNull($this->pingMessage($thread));
    }

    public function test_fails_when_neither_thread_nor_subject_is_given(): void
    {
        $sender = User::factory()->create();
        $user = User::factory()->create();

        $this->runPing(['--from' => $sender->id, '--user' => [$user->id]])
            ->assertFailed();

        $this->assertNoPingMessagesExist();
    }

    public function test_fails_when_both_thread_and_subject_are_given(): void
    {
        $sender = User::factory()->create();
        $thread = $this->service->newThread('S', $sender, $this->envelope());

        $this->runPing([
            '--thread' => $thread->id,
            '--subject' => 'X',
            '--from' => $sender->id,
            '--user' => [$sender->id],
        ])->assertFailed();

        $this->assertNoPingMessagesExist();
    }

    public function test_fails_when_thread_not_found(): void
    {
        $sender = User::factory()->create();
        $user = User::factory()->create();

        $this->runPing([
            '--thread' => '00000000-0000-0000-0000-000000000000',
            '--from' => $sender->id,
            '--user' => [$user->id],
        ])->assertFailed();

        $this->assertNoPingMessagesExist();
    }

    public function test_fails_when_a_pinged_user_is_not_a_participant(): void
    {
        $sender = User::factory()->create();
        $outsider = User::factory()->create();
        $thread = $this->service->newThread('S', $sender, $this->envelope());

        $this->runPing([
            '--thread' => $thread->id,
            '--from' => $sender->id,
            '--user' => [$outsider->id],
        ])->assertFailed();

        $this->assertNoPingMessagesExist();
    }

    public function test_fails_when_sender_is_not_a_participant(): void
    {
        $creator = User::factory()->create();
        $participant = User::factory()->create();
        $outsider = User::factory()->create();
        $thread = $this->service->newThread('S', $creator, $this->envelope(), [$participant->id]);

        $this->runPing([
            '--thread' => $thread->id,
            '--from' => $outsider->id,
            '--user' => [$participant->id],
        ])->assertFailed();

        $this->assertNoPingMessagesExist();
    }

    public function test_fails_without_users(): void
    {
        $sender = User::factory()->create();
        $thread = $this->service->newThread('S', $sender, $this->envelope());

        $this->runPing(['--thread' => $thread->id, '--from' => $sender->id])
            ->assertFailed();

        $this->assertNoPingMessagesExist();
    }

    public function test_fails_when_from_is_missing(): void
    {
        $sender = User::factory()->create();
        $thread = $this->service->newThread('S', $sender, $this->envelope());

        $this->runPing(['--thread' => $thread->id, '--user' => [$sender->id]])
            ->assertFailed();

        $this->assertNoPingMessagesExist();
    }

    public function test_fails_when_sender_is_not_found(): void
    {
        $creator = User::factory()->create();
        $participant = User::factory()->create();
        $thread = $this->service->newThread('S', $creator, $this->envelope(), [$participant->id]);

        $this->runPing([
            '--thread' => $thread->id,
            '--from' => (string) Str::uuid(),
            '--user' => [$participant->id],
        ])->assertFailed();

        $this->assertNoPingMessagesExist();
    }

    public function test_fails_when_a_pinged_user_does_not_exist(): void
    {
        $sender = User::factory()->create();
        $thread = $this->service->newThread('S', $sender, $this->envelope());

        $this->runPing([
            '--thread' => $thread->id,
            '--from' => $sender->id,
            '--user' => [(string) Str::uuid()],
        ])->assertFailed();

        $this->assertNoPingMessagesExist();
    }

    public function test_create_mode_fails_gracefully_when_note_exceeds_max_length(): void
    {
        $sender = User::factory()->create();
        $recipient = User::factory()->create();

        $this->runPing([
            '--from' => $sender->id,
            '--subject' => 'Heads up',
            '--user' => [$recipient->id],
            '--note' => str_repeat('a', 281),
        ])->assertFailed();

        $this->assertNoPingMessagesExist();
        $this->assertDatabaseMissing('threads', ['subject' => 'Heads up']);
    }

    private function assertNoPingMessagesExist(): void
    {
        $this->assertFalse(
            Message::all()->contains(fn (Message $m) => Arr::get($m->body, 'type') === 'ping'),
            'Expected no ping messages to have been created.',
        );
    }
}
