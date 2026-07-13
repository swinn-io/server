<?php

namespace Tests\Unit;

use App\Interfaces\ContactServiceInterface;
use App\Interfaces\MessageServiceInterface;
use App\Models\Participant;
use App\Models\Thread;
use App\Models\User;
use App\Notifications\MessageCreated;
use App\Notifications\ParticipantCreated;
use App\Notifications\ThreadCreated;
use Database\Seeders\MessagingSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

class MessageTest extends TestCase
{
    use WithFaker;

    /**
     * @var MessageServiceInterface
     */
    private $service;

    /**
     * @var ContactServiceInterface
     */
    private $contactService;

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
     * A valid typed message envelope for service-layer writes.
     *
     * @return array<string, mixed>
     */
    private function envelope(string $mood = 'happy'): array
    {
        return ['type' => 'mood', 'version' => '1.0', 'payload' => ['mood' => $mood]];
    }

    /**
     * @param  Collection<int, User>  $users
     */
    private function firstOf(Collection $users): User
    {
        /** @var User $user */
        $user = $users->first();

        return $user;
    }

    /**
     * @param  Collection<int, User>  $users
     */
    private function lastOf(Collection $users): User
    {
        /** @var User $user */
        $user = $users->last();

        return $user;
    }

    /**
     * @param  Collection<int, User>  $users
     */
    private function randomOf(Collection $users): User
    {
        /** @var User $user */
        $user = $users->random();

        return $user;
    }

    /**
     * @param  array<mixed>  $retrieve
     * @return array<string, mixed>
     */
    private function firstParticipant(array $retrieve): array
    {
        /** @var array<string, mixed> $participant */
        $participant = Arr::get($retrieve, 'participants.0', []);

        return $participant;
    }

    /**
     * @param  array<mixed>  $retrieve
     * @return array<int, mixed>
     */
    private function messagesOf(array $retrieve): array
    {
        /** @var array<int, mixed> $messages */
        $messages = Arr::get($retrieve, 'messages', []);

        return $messages;
    }

    /**
     * Check if threads method returns pagination of threads models of a user model.
     *
     * @return void
     */
    public function test_service_method_threads()
    {
        $user = User::factory()->create();
        $create = 5;

        // Create 5 threads for created user.
        for ($i = 1; $i <= $create; $i++) {
            $this->service->newThread("Test Thread {$i}", $user, $this->envelope());
        }

        // Add a couple of extra messages to the first thread so the count
        // assertion below can't be satisfied by a stray value like 1.
        $firstThread = Thread::forUser($user->id)->oldest('updated_at')->firstOrFail();
        $this->service->newMessage($firstThread, $user, $this->envelope());
        $this->service->newMessage($firstThread, $user, $this->envelope());

        $allThreads = $this->service->threads($user);
        $modelName = get_class($allThreads->items()[0]);

        $this->assertEquals($create, $allThreads->total());
        $this->assertEquals(Thread::class, $modelName);

        /** @var Thread $threadWithExtraMessages */
        $threadWithExtraMessages = $allThreads->firstWhere('id', $firstThread->id);
        $this->assertSame(3, $threadWithExtraMessages->messages_count);
        $this->assertSame(
            $threadWithExtraMessages->messages()->count(),
            $threadWithExtraMessages->messages_count
        );
    }

    /**
     * Check if undereadThreads method returns unread message threads.
     *
     * @return void
     */
    public function test_service_method_unread_threads()
    {
        // Random participant for utilizing user id
        $participant = Participant::inRandomOrder()->firstOrFail();
        // Chosen user to test
        $user = User::findOrFail($participant->user_id);

        // All threads of a user that participated
        $allThreads = $this->service->threads($user);
        // Retrieve all the participation of a user
        $participated = Participant::where([
            'user_id' => $user->id,
        ]);

        // Cross-check for thread and participation counts.
        $this->assertEquals($allThreads->total(), $participated->count());

        // Create messages after delaying one second
        sleep(1);
        $allThreads->random(rand(0, $allThreads->count()))->each(function ($willBeMessaged) {
            $sender = User::inRandomOrder()->firstOrFail();
            $this->service->newMessage($willBeMessaged, $sender, $this->envelope());
        });

        // Collection of unread threads to match
        $unreadThreads = $this->service->unreadThreads($user);

        $participated = $participated->get();
        $filtered = $participated->filter(function ($participation) {
            $thread = Thread::where('id', $participation->thread_id)->firstOrFail();

            return
                // Last read is null and the user has never read the thread
                $participation->last_read === null
                ||
                // Or last read datetime is less than last message datetime
                ($thread->updated_at !== null && $thread->updated_at->greaterThan($participation->last_read));
        });

        $this->assertEquals($filtered->count(), $unreadThreads->count());
    }

    /**
     * Check if thread method returns a thread model.
     *
     * @return void
     */
    public function test_service_method_thread()
    {
        $user = User::factory()->create();
        $thread = $this->service->newThread('New Thread!', $user, $this->envelope());
        $retrieve = $this->service->thread($thread->id)->toArray();

        $this->assertArrayHasKey('messages', $retrieve);
        $this->assertArrayHasKey('participants', $retrieve);
        $this->assertArrayHasKey('user', $this->firstParticipant($retrieve));

        $this->assertEquals($thread->subject, Arr::get($retrieve, 'subject'));
        $this->assertCount(1, $this->messagesOf($retrieve));
        $this->assertEquals($user->name, Arr::get($retrieve, 'participants.0.user.name'));
    }

    /**
     * Check if thread participants are same as retrieved by thread method.
     *
     * @return void
     */
    public function test_service_method_thread_participants()
    {
        $users = User::factory()
            ->count(5)
            ->create();
        /** @var array<int, string> $recipients */
        $recipients = $users->pluck('id')->toArray();
        $create = $this->service->newThread('New Thread!', $this->firstOf($users), $this->envelope(), $recipients);
        $thread = $this->service->thread($create->id);
        $participants = $this->service->threadParticipants($create->id);

        $useThreadMethod = json_encode($thread->participants->sortBy('id')->toArray());
        $useThreadParticipantsMethod = json_encode($participants->sortBy('id')->toArray());

        $this->assertSame($useThreadMethod, $useThreadParticipantsMethod);
    }

    /**
     * Check id newThread method creates a new thread.
     *
     * @return void
     */
    public function test_service_method_new_thread()
    {
        $user = User::factory()->create();
        $thread = $this->service->newThread('New Thread!', $user, $this->envelope());
        $retrieve = $this->service->thread($thread->id)->toArray();

        $this->assertArrayHasKey('messages', $retrieve);
        $this->assertArrayHasKey('participants', $retrieve);
        $this->assertArrayHasKey('user', $this->firstParticipant($retrieve));

        $this->assertEquals($thread->subject, Arr::get($retrieve, 'subject'));
        $this->assertCount(1, $this->messagesOf($retrieve));
        $this->assertEquals($user->name, Arr::get($retrieve, 'participants.0.user.name'));
    }

    /**
     * Check id newMessage method creates a new messages.
     *
     * @return void
     */
    public function test_service_method_new_message()
    {
        $users =
            User::factory()
                ->count(5)
                ->create();
        /** @var array<int, string> $recipients */
        $recipients = $users->pluck('id')->toArray();
        $thread = $this->service->newThread('New Thread!', $this->firstOf($users), $this->envelope(), $recipients);
        $lastMessage = null;
        $messageNum = rand(2, 10);

        for ($i = 1; $i <= $messageNum; $i++) {
            $lastMessage = $this->service->newMessage($thread, $this->randomOf($users), $this->envelope('meh'));
            sleep(1); // For message sorting
        }

        $retrieve = $this->service->thread($thread->id)->toArray();

        $this->assertArrayHasKey('messages', $retrieve);
        $this->assertArrayHasKey('participants', $retrieve);
        $this->assertArrayHasKey('user', $this->firstParticipant($retrieve));

        $this->assertEquals($thread->subject, Arr::get($retrieve, 'subject'));
        $this->assertCount(($messageNum + 1), $this->messagesOf($retrieve));

        $lastMessage = Arr::get($lastMessage->toArray(), 'body');
        $lastInsertedMessage = Arr::get($retrieve, 'messages.0.body');

        $this->assertEquals($lastMessage, $lastInsertedMessage);
    }

    /**
     * Check if markAsRead method updates last read attribute.
     *
     * @return void
     */
    public function test_service_method_mark_as_read()
    {
        $users = User::factory()
            ->count(2)
            ->create();
        $messageNum = rand(1, 10);

        for ($i = 1; $i <= $messageNum; $i++) {
            /** @var array<int, string> $words */
            $words = $this->faker->words();
            $title = Str::title(implode(' ', $words));
            $this->service->newThread($title, $this->firstOf($users), $this->envelope(), [$this->lastOf($users)->id]);
        }

        $retrieve = $this->service->unreadThreads($this->lastOf($users));

        $this->assertCount($messageNum, $retrieve);

        // Mark as read each threads.
        $retrieve->each(function ($thread) use ($users) {
            $this->service->markAsRead($thread, $this->lastOf($users));
        });
        $retrieve = $this->service->unreadThreads($this->lastOf($users));
        $this->assertCount(0, $retrieve);
    }

    /**
     * Check if markAsReadAll method updated all last read attributes of Participant models.
     *
     * @return void
     */
    public function test_service_method_mark_as_read_all()
    {
        $users = User::factory()
            ->count(2)
            ->create();
        $messageNum = rand(1, 10);

        for ($i = 1; $i <= $messageNum; $i++) {
            /** @var array<int, string> $words */
            $words = $this->faker->words();
            $title = Str::title(implode(' ', $words));
            $this->service->newThread($title, $this->firstOf($users), $this->envelope(), [$this->lastOf($users)->id]);
        }

        $retrieve = $this->service->unreadThreads($this->lastOf($users));

        $this->assertCount($messageNum, $retrieve);

        // Mark as read all threads.
        $this->service->markAsReadAll($this->lastOf($users));
        $retrieve = $this->service->unreadThreads($this->lastOf($users));
        $this->assertCount(0, $retrieve);
    }

    /**
     * Check id addParticipant method adds new participants to the thread.
     *
     * @return void
     */
    public function test_service_method_add_participant()
    {
        $user = User::factory()->create();
        $thread = $this->service->newThread('New Thread!', $user, $this->envelope());

        $userNumber = 5;
        $newParticipants = User::factory()
            ->count($userNumber)
            ->create();

        $newParticipants->each(function ($participant) use ($thread) {
            $this->service->addParticipant($thread, $participant);
        });

        $participants = $this->service->threadParticipants($thread->id);
        $contacts = $this->contactService->contacts($user)->total();

        $this->assertCount($newParticipants->count() + 1, $participants);
        $this->assertEquals($userNumber, $contacts);
    }

    /**
     * Fully resolve a notification's array representation (including nested
     * JsonResource/AnonymousResourceCollection instances) into plain arrays so
     * it can be inspected with Arr::get(), the same shape it takes once
     * serialized to JSON for the broadcast channel.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function resolvedPayload(array $payload): array
    {
        /** @var array<string, mixed> $resolved */
        $resolved = json_decode((string) json_encode($payload), true);

        return $resolved;
    }

    /**
     * Check that the broadcast/notification payloads carry the sender's/participant's
     * "user" relation, not just the "user_id" foreign key. Models handed to
     * MessageCreated/ThreadCreated/ParticipantCreated must have their "user"
     * relation eager-loaded, otherwise JsonResource::whenLoaded('user') silently
     * omits the "user" key from the resolved payload.
     *
     * @return void
     */
    public function test_notifications_carry_eager_loaded_user_relation_in_payload()
    {
        Notification::fake();

        $sender = User::factory()->create(['notify_via' => ['broadcast']]);
        $recipient = User::factory()->create(['notify_via' => ['broadcast']]);

        $thread = $this->service->newThread('New Thread!', $sender, $this->envelope(), [$recipient->id]);

        Notification::assertSentTo($recipient, ThreadCreated::class, function (ThreadCreated $notification) {
            $payload = $this->resolvedPayload($notification->toArray($notification));
            /** @var array<int, array<string, mixed>> $participants */
            $participants = Arr::get($payload, 'payload.attributes.participants', []);
            $participantUserNames = collect($participants)
                ->pluck('attributes.user.attributes.name')
                ->filter();

            return $participantUserNames->count() === 2;
        });

        // The thread's very first message is created before any recipients are
        // attached as participants (see MessageService::newThread), so send a
        // follow-up message on the now-populated thread to exercise the
        // MessageCreated notification path.
        $this->service->newMessage($thread, $sender, $this->envelope('meh'));

        Notification::assertSentTo($recipient, MessageCreated::class, function (MessageCreated $notification) use ($sender) {
            $payload = $this->resolvedPayload($notification->toArray($notification));

            return Arr::get($payload, 'payload.attributes.user.attributes.name') === $sender->name;
        });

        $newParticipant = User::factory()->create(['notify_via' => ['broadcast']]);
        $this->service->addParticipant($thread, $newParticipant);

        Notification::assertSentTo($recipient, ParticipantCreated::class, function (ParticipantCreated $notification) use ($newParticipant) {
            $payload = $this->resolvedPayload($notification->toArray($notification));

            return Arr::get($payload, 'payload.attributes.user.attributes.name') === $newParticipant->name;
        });
    }

    /**
     * Reproduces the real dispatch path: ShouldQueue notifications are
     * serialized and restored via SerializesModels before toArray() runs
     * (this happens even on the "sync" queue connection), which drops any
     * relation set only via setRelation() and never actually eager-loaded.
     * ThreadCreated::toArray() must reload participants.user itself rather
     * than assume the caller's in-memory relations survive that round-trip.
     *
     * @return void
     */
    public function test_thread_created_toarray_reloads_participant_user_after_serialization_round_trip()
    {
        Notification::fake();

        $sender = User::factory()->create(['notify_via' => ['broadcast']]);
        $recipient = User::factory()->create(['notify_via' => ['broadcast']]);

        $thread = $this->service->newThread('Serialization Round Trip', $sender, $this->envelope(), [$recipient->id]);

        // Fetch a bare copy with participants loaded but participants.user NOT
        // loaded, mirroring what SerializesModels restores after unserialize().
        $bareThread = Thread::with('participants')->findOrFail($thread->id);

        $notification = new ThreadCreated($bareThread);
        $payload = $this->resolvedPayload($notification->toArray($recipient));

        /** @var array<int, array<string, mixed>> $participants */
        $participants = Arr::get($payload, 'payload.attributes.participants', []);
        $participantUserNames = collect($participants)
            ->pluck('attributes.user.attributes.name')
            ->filter();

        $this->assertCount(2, $participantUserNames);
    }
}
