<?php

namespace App\Services;

use App\Interfaces\ContactServiceInterface;
use App\Interfaces\MessageServiceInterface;
use App\Models\Message;
use App\Models\Participant;
use App\Models\Thread;
use App\Models\User;
use App\Notifications\MessageCreated;
use App\Notifications\ParticipantCreated;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

class MessageService implements MessageServiceInterface
{
    /**
     * @var ContactServiceInterface
     */
    public ContactServiceInterface $contactService;

    /**
     * MessageService constructor.
     * @param ContactServiceInterface $contactService
     */
    public function __construct(ContactServiceInterface $contactService)
    {
        $this->contactService = $contactService;
    }

    /**
     * All threads that user is participating in.
     *
     * @param User $user
     * @return LengthAwarePaginator
     */
    public function threads(User $user): LengthAwarePaginator
    {
        return Thread::forUser($user->id)->latest('updated_at')->paginate();
    }

    /**
     * All threads that user is participating in, with new messages.
     *
     * @param User $user
     * @return Collection
     */
    public function unreadThreads(User $user): Collection
    {
        return Thread::forUserWithNewMessages($user->id)->latest('updated_at')->get();
    }

    /**
     * Retrieve a thread.
     *
     * @param string $thread_id
     * @return Thread
     * @throws ModelNotFoundException
     */
    public function thread(string $thread_id): Thread
    {
        return Thread::with(['messages', 'participants.user'])->find($thread_id);
    }

    /**
     * User ids that are associated with the thread.
     *
     * @param string $thread_id
     * @return Collection
     */
    public function threadParticipants(string $thread_id): Collection
    {
        return Thread::with('participants.user')->find($thread_id)->participants;
    }

    /**
     * New message thread.
     *
     * @param string $subject
     * @param User $user
     * @param array $content
     * @param null|array $recipients
     * @return Thread
     */
    public function newThread(string $subject, User $user, array $content, ?array $recipients = []): Thread
    {
        /** @var $thread Thread */
        $thread = Thread::create([
            'subject' => $subject,
        ]);

        // Recipients are participants too
        collect($recipients)
            ->map(function ($recipient) {
                return User::find($recipient);
            })
            ->filter()
            ->each(function ($recipient) use ($thread) {
                $this->addParticipant($thread, $recipient);
            });

        $this->newMessage($thread, $user, $content);

        return $thread;
    }

    /**
     * New message.
     *
     * @param Thread $thread
     * @param User $user
     * @param array $content
     * @return Message
     */
    public function newMessage(Thread $thread, User $user, array $content): Message
    {
        $activatedParticipants = $thread
            ->activateAllParticipants()
            ->pluck('user_id')
            ->push($user->id)
            ->unique()
            ->toArray();

        $message = Message::create([
            'thread_id' => $thread->id,
            'user_id' => $user->id,
            'body' => $content,
        ]);

        // Make participant if not
        $this->addParticipant($thread, $user, true);

        $recipients = User::find($activatedParticipants);

        Notification::send($recipients, new MessageCreated($message));

        return $message;
    }

    /**
     * Mark as read a tread of a user.
     *
     * @param Thread $thread
     * @param User $user
     * @return Participant
     */
    public function markAsRead(Thread $thread, User $user): Participant
    {
        return $thread->markAsRead($user->id);
    }

    /**
     * Mark as read all messages of a user.
     *
     * @param User $user
     * @return bool
     */
    public function markAsReadAll(User $user): bool
    {
        return Participant::where([
            'user_id' => $user->id,
        ])->update([
            'last_read' => now(),
        ]);
    }

    /**
     * Mark as read all messages of a user.
     *
     * @param Thread $thread
     * @param User $user
     * @param bool $mark_as_read
     * @return Participant
     */
    public function addParticipant(Thread $thread, User $user, bool $mark_as_read = false): Participant
    {
        $return = $thread->participants()->updateOrCreate([
            'user_id' => $user->id,
            'thread_id' => $thread->id,
        ],
            $mark_as_read ? ['last_read' => now()] : []);

        $users = $thread->users()->get();

        $users->each(function ($user) use ($users){
            $users->each(function ($contact) use ($user){
                if(!$user->is($contact))
                {
                    $this->contactService->addContact($user, $contact);
                }
            });
        });

        Notification::send($users, new ParticipantCreated($thread));

        return $return;
    }
}
