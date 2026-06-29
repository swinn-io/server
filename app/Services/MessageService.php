<?php

namespace App\Services;

use App\Exceptions\InvalidEnvelopeException;
use App\Interfaces\ContactServiceInterface;
use App\Interfaces\MessageServiceInterface;
use App\Models\Message;
use App\Models\Participant;
use App\Models\Thread;
use App\Models\User;
use App\Notifications\MessageCreated;
use App\Notifications\ParticipantCreated;
use App\Notifications\ThreadCreated;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

class MessageService implements MessageServiceInterface
{
    public ContactServiceInterface $contactService;

    /**
     * MessageService constructor.
     */
    public function __construct(ContactServiceInterface $contactService)
    {
        $this->contactService = $contactService;
    }

    /**
     * All threads that user is participating in.
     */
    public function threads(User $user): LengthAwarePaginator
    {
        return Thread::forUser($user->id)->with('participants.user')->latest('updated_at')->paginate();
    }

    /**
     * All threads that user is participating in, with new messages.
     */
    public function unreadThreads(User $user): Collection
    {
        return Thread::forUserWithNewMessages($user->id)->latest('updated_at')->get();
    }

    /**
     * Retrieve a thread.
     *
     *
     * @throws ModelNotFoundException
     */
    public function thread(string $thread_id): Thread
    {
        return Thread::with(['messages', 'participants.user'])->find($thread_id);
    }

    /**
     * User ids that are associated with the thread.
     */
    public function threadParticipant(string $thread_id): Collection
    {
        return Thread::with('participants.user')->find($thread_id)->participants;
    }

    /**
     * User ids that are associated with the thread.
     */
    public function threadParticipants(string $thread_id): Collection
    {
        return Thread::with('participants.user')->find($thread_id)->participants;
    }

    /**
     * New message thread.
     */
    public function newThread(string $subject, User $user, array $content, ?array $recipients = []): Thread
    {
        $this->assertValidEnvelope($content);

        /** @var $thread Thread */
        $thread = Thread::create([
            'subject' => $subject,
        ]);

        $message = $this->newMessage($thread, $user, $content);

        // Recipients are participants too
        $recipients = collect($recipients)
            ->map(function ($recipient) {
                return User::find($recipient);
            })
            ->add($user)
            ->unique('id')
            ->map(function ($recipient) use ($thread) {
                return $this->addParticipant($thread, $recipient);
            });

        $thread->setRelation('messages', collect([$message]));
        $thread->setRelation('participants', $recipients);
        $users = User::find($recipients->pluck('user_id'));
        Notification::send($users, new ThreadCreated($thread));

        return $thread;
    }

    /**
     * New message.
     */
    public function newMessage(Thread $thread, User $user, array $content): Message
    {
        $this->assertValidEnvelope($content);

        $message = Message::create([
            'thread_id' => $thread->id,
            'user_id' => $user->id,
            'body' => $content,
        ]);

        $recipients = $thread->users()->get();

        Notification::send($recipients, new MessageCreated($message));

        return $message;
    }

    /**
     * Mark as read a tread of a user.
     */
    public function markAsRead(Thread $thread, User $user): Participant
    {
        $thread->markAsRead($user->id);

        return $thread->participants()->where('user_id', $user->id)->first();
    }

    /**
     * Mark as read all messages of a user.
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
     */
    public function addParticipant(Thread $thread, User $user, bool $mark_as_read = false): Participant
    {
        $return = $thread->participants()->updateOrCreate([
            'user_id' => $user->id,
            'thread_id' => $thread->id,
        ],
            $mark_as_read ? ['last_read' => now()] : []);

        $users = $thread->users()->get();

        $this->contactService->setContacts($users);

        Notification::send($users, new ParticipantCreated($return));

        return $return;
    }

    /**
     * Validate a typed envelope, throwing on failure.
     */
    private function assertValidEnvelope(array $envelope): void
    {
        $error = app(TypeRegistry::class)->validate($envelope);

        if ($error !== null) {
            throw new InvalidEnvelopeException($error);
        }
    }
}
