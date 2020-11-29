<?php

namespace Tests\Unit;

use App\Interfaces\MessageServiceInterface;
use App\Models\Participant;
use App\Models\Thread;
use App\Models\User;
use Database\Seeders\UserSeeder;
use Database\Seeders\MessagingSeeder;
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
     * Check if all method returns pagination of thread models.
     *
     * @return void
     */
    public function testServiceMethodAll()
    {
        $user = User::factory()->create();
        $create = 5;

        // Create 5 threads for created user.
        for ($i = 1; $i <= $create; $i++)
        {
            $this->service->newThread("Test Thread {$i}", $user, ["some" => "data"]);
        }

        $allThreads = $this->service->all($user);
        $modelName = get_class(Arr::get($allThreads->items(), 0));

        $this->assertEquals($create, $allThreads->total());
        $this->assertEquals(Thread::class, $modelName);
    }

    /**
     * Check if threads method returns pagination of threads models of a user model.
     *
     * @return void
     */
    public function testServiceMethodThreads()
    {
        $thread = Thread::with('participants.user')->inRandomOrder()->first();
        $participant = $thread->participants->first();
        $threads = $this->service->threads($participant->user);

        $count = Participant::where([
            'user_id' => $participant->user_id,
        ])->count();

        $this->assertEquals($count, $threads->total());

        $modelName = get_class(Arr::get($threads->items(), 0));
        $this->assertEquals(Thread::class, $modelName);
    }

    /**
     * Check if undereadThreads method returns unread message threads.
     *
     * @return void
     */
    public function testServiceMethodUnreadThreads()
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
            $this->service->newMessage($willBeMessaged, $sender, ['some' => 'content']);
        });

        // Collection of unread threads to match
        $unreadThreads = $this->service->unreadThreads($user);

        $participated = $participated->get();
        $filtered = $participated->filter(function ($participation) {
            $thread = Thread::where('id', $participation->thread_id)->first();

            return
                // Last read is null and the user has never read the thread
                null === $participation->last_read
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
    public function testServiceMethodThread()
    {
        $user = User::factory()->create();
        $thread = $this->service->newThread('New Thread!', $user, ['some' => 'data']);
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
    public function testServiceMethodThreadParticipants()
    {
        $users = User::factory()
            ->count(5)
            ->create();
        $recipients = $users->pluck('id')->toArray();
        $create = $this->service->newThread('New Thread!', $users->first(), ['some' => 'data'], $recipients);
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
    public function testServiceMethodNewThread()
    {
        $user = User::factory()->create();
        $thread = $this->service->newThread('New Thread!', $user, ['some' => 'data']);
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
    public function testServiceMethodNewMessage()
    {
        $users =
            User::factory()
                ->count(5)
                ->create();;
        $recipients = $users->pluck('id');
        $thread = $this->service->newThread('New Thread!', $users->first(), ['some' => 'data'], $recipients->toArray());
        $lastMessage = null;
        $messageNum = rand(1, 10);

        for ($i = 1; $i <= $messageNum; $i++) {
            $lastMessage = $this->service->newMessage($thread, $users->random(), ['some', "content #{$i}"]);
            sleep(1); // For message sorting
        }

        $retrieve = $this->service->thread($thread->id)->toArray();

        $this->assertArrayHasKey('messages', $retrieve);
        $this->assertArrayHasKey('participants', $retrieve);
        $this->assertArrayHasKey('user', Arr::get($retrieve, 'participants.0'));

        $this->assertEquals($thread->subject, Arr::get($retrieve, 'subject'));
        $this->assertCount(($messageNum + 1), Arr::get($retrieve, 'messages'));

        $lastMessage = json_encode(Arr::get($lastMessage->toArray(), 'body'));
        $lastInsertedMessage = json_encode(Arr::get(Arr::first(Arr::get($retrieve, 'messages')), 'body'));

        $this->assertSame($lastMessage, $lastInsertedMessage);
    }

    /**
     * Check if markAsRead method updates last read attribute.
     *
     * @return void
     */
    public function testServiceMethodMarkAsRead()
    {
        $users = User::factory()
            ->count(2)
            ->create();;
        $messageNum = rand(1, 10);

        for ($i = 1; $i <= $messageNum; $i++) {
            $title = Str::title(implode(' ', $this->faker->words));
            $this->service->newThread($title, $users->first(), ['some' => 'data'], [$users->last()->id]);
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
    public function testServiceMethodMarkAsReadAll()
    {
        $users = User::factory()
            ->count(2)
            ->create();
        $messageNum = rand(1, 10);

        for ($i = 1; $i <= $messageNum; $i++) {
            $title = Str::title(implode(' ', $this->faker->words));
            $this->service->newThread($title, $users->first(), ['some' => 'data'], [$users->last()->id]);
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
    public function testServiceMethodAddParticipant()
    {
        $user = User::factory()->create();
        $thread = $this->service->newThread('New Thread!', $user, ['some' => 'data']);

        $newParticipants = User::factory()
            ->count(5)
            ->create();

        $newParticipants->each(function ($participant) use ($thread) {
            $this->service->addParticipant($thread, $participant);
        });

        $participants = $this->service->threadParticipants($thread->id);

        $this->assertCount($newParticipants->count() + 1, $participants);
    }
}
