<?php

namespace App\Services;

use App\Interfaces\MessageServiceInterface;
use App\Models\Message;
use App\Models\Participant;
use App\Models\Thread;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;

class MessageService implements MessageServiceInterface
{
    /**
     * All threads, ignore deleted/archived participants.
     *
     * @return LengthAwarePaginator
     */
    public function all(): LengthAwarePaginator
    {
        return Thread::getAllLatest()->paginate();
    }

    /**
     * All threads that user is participating in.
     *
     * @param string $user_id
     * @return LengthAwarePaginator
     */
    public function threads(string $user_id): LengthAwarePaginator
    {
        return Thread::forUser($user_id)->latest('updated_at')->paginate();
    }

    /**
     * All threads that user is participating in, with new messages.
     *
     * @param string $user_id
     * @return Collection
     */
    public function unreadThreads(string $user_id): Collection
    {
        return Thread::forUserWithNewMessages($user_id)->latest('updated_at')->get();
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
        return Thread::with(['messages', 'participants'])->findOrFail($thread_id);
    }

    /**
     * User ids that are associated with the thread.
     *
     * @param string $thread_id
     * @return Collection
     */
    public function threadParticipants(string $thread_id): Collection
    {
        return Thread::findOrFail($thread_id)->participants()->get();
    }

    /**
     * New message thread.
     *
     * @param string $subject
     * @param string $user_id
     * @param array $content
     * @param null|array $recipients
     * @return Thread
     */
    public function newThread(string $subject, string $user_id, array $content, ?array $recipients = []): Thread
    {
        $thread = Thread::create([
            'subject' => $subject,
        ]);

        $thread->message()->create([
            'thread_id' => $thread->id,
            'user_id' => $user_id,
            'body' => $content,
        ]);

        // Sender
        Participant::create([
            'thread_id' => $thread->id,
            'user_id' => $user_id,
            'last_read' => now(),
        ]);

        collect($recipients)->each(function ($item) use ($thread) {
            $thread->addParticipant($item);
        });

        return $thread;
    }

    /**
     * New message.
     *
     * @param string $thread_id
     * @param string $user_id
     * @param array $content
     * @return Message
     * @throws ModelNotFoundException
     */
    public function newMessage(string $thread_id, string $user_id, array $content): Message
    {
        $thread = Thread::findOrFail($thread_id);

        $thread->activateAllParticipants();

        $message = Message::create([
            'thread_id' => $thread->id,
            'user_id' => $user_id,
            'body' => $content,
        ]);

        // Add replier as a participant
        $participant = Participant::firstOrCreate([
            'thread_id' => $thread->id,
            'user_id' => $user_id,
            'last_read' => now(),
        ]);
        $participant->save();

        return $message;
    }

    /**
     * Mark as read all messages of a user.
     *
     * @param Thread $thread
     * @param string $user_id
     */
    public function markAsRead(Thread $thread, string $user_id): void
    {
        $thread->markAsRead($user_id);
    }

    /**
     * Mark as read all messages of a user.
     *
     * @param Thread $thread
     * @param string $user_id
     */
    public function addParticipant(Thread $thread, string $user_id): void
    {
        $thread->addParticipant($user_id);
    }
}
