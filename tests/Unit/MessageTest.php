<?php

namespace Tests\Unit;

use App\Interfaces\ContactServiceInterface;
use App\Interfaces\MessageServiceInterface;
use App\Models\Participant;
use App\Models\Thread;
use App\Models\User;
use Database\Seeders\MessagingSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Arr;
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

        $allThreads = $this->service->threads($user);
        $modelName = get_class(Arr::get($allThreads->items(), 0));

        $this->assertEquals($create, $allThreads->total());
        $this->assertEquals(Thread::class, $modelName);
    }

    /**
     * Check if undereadThreads method returns unread message threads.
     *
     * @return void
     */
    public function test_service_method_unread_threads()
    {
        // Random participant for utilizing user id
        $participant = Participant::inRandomOrder()->first();
        // Chosen user to test
        $user = User::find($participant->user_id);

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
            $sender = User::inRandomOrder()->first();
            $this->service->newMessage($willBeMessaged, $sender, $this->envelope());
        });

        // Collection of unread threads to match
        $unreadThreads = $this->service->unreadThreads($user);

        $participated = $participated->get();
        $filtered = $participated->filter(function ($participation) {
            $thread = Thread::where('id', $participation->thread_id)->first();

            return
                // Last read is null and the user has never read the thread
                $participation->last_read === null
                ||
                // Or last read datetime is less than last message datetime
                $thread->updated_at->greaterThan($participation->last_read);
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
        $this->assertArrayHasKey('user', Arr::get($retrieve, 'participants.0'));

        $this->assertEquals($thread->subject, Arr::get($retrieve, 'subject'));
        $this->assertCount(1, Arr::get($retrieve, 'messages'));
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
        $recipients = $users->pluck('id')->toArray();
        $create = $this->service->newThread('New Thread!', $users->first(), $this->envelope(), $recipients);
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
        $this->assertArrayHasKey('user', Arr::get($retrieve, 'participants.0'));

        $this->assertEquals($thread->subject, Arr::get($retrieve, 'subject'));
        $this->assertCount(1, Arr::get($retrieve, 'messages'));
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
        $recipients = $users->pluck('id');
        $thread = $this->service->newThread('New Thread!', $users->first(), $this->envelope(), $recipients->toArray());
        $lastMessage = null;
        $messageNum = rand(2, 10);

        for ($i = 1; $i <= $messageNum; $i++) {
            $lastMessage = $this->service->newMessage($thread, $users->random(), $this->envelope('meh'));
            sleep(1); // For message sorting
        }

        $retrieve = $this->service->thread($thread->id)->toArray();

        $this->assertArrayHasKey('messages', $retrieve);
        $this->assertArrayHasKey('participants', $retrieve);
        $this->assertArrayHasKey('user', Arr::get($retrieve, 'participants.0'));

        $this->assertEquals($thread->subject, Arr::get($retrieve, 'subject'));
        $this->assertCount(($messageNum + 1), Arr::get($retrieve, 'messages'));

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
            $title = Str::title(implode(' ', $this->faker->words));
            $this->service->newThread($title, $users->first(), $this->envelope(), [$users->last()->id]);
        }

        $retrieve = $this->service->unreadThreads($users->last());

        $this->assertCount($messageNum, $retrieve);

        // Mark as read each threads.
        $retrieve->each(function ($thread) use ($users) {
            $this->service->markAsRead($thread, $users->last());
        });
        $retrieve = $this->service->unreadThreads($users->last());
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
            $title = Str::title(implode(' ', $this->faker->words));
            $this->service->newThread($title, $users->first(), $this->envelope(), [$users->last()->id]);
        }

        $retrieve = $this->service->unreadThreads($users->last());

        $this->assertCount($messageNum, $retrieve);

        // Mark as read all threads.
        $this->service->markAsReadAll($users->last());
        $retrieve = $this->service->unreadThreads($users->last());
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
}
