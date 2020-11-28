<?php

namespace App\Interfaces;

use App\Models\Message;
use App\Models\Participant;
use App\Models\Thread;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;

interface MessageServiceInterface
{
    /**
     * All threads, ignore deleted/archived participants.
     *
     * @return LengthAwarePaginator
     */
    public function all(): LengthAwarePaginator;

    /**
     * All threads that user is participating in.
     *
     * @param string $user_id
     * @return LengthAwarePaginator
     */
    public function threads(string $user_id): LengthAwarePaginator;

    /**
     * All threads that user is participating in, with new messages.
     *
     * @param string $user_id
     * @return Collection
     * @throws ModelNotFoundException
     */
    public function unreadThreads(string $user_id): Collection;

    /**
     * Retrieve a thread.
     *
     * @param string $thread_id
     * @return Thread
     */
    public function thread(string $thread_id): Thread;

    /**
     * User ids that are associated with the thread.
     *
     * @param string $thread_id
     * @return Collection
     */
    public function threadParticipants(string $thread_id): Collection;

    /**
     * New message thread.
     *
     * @param string $subject
     * @param User $user
     * @param array $content
     * @param array $recipients
     * @return Thread
     */
    public function newThread(string $subject, User $user, array $content, array $recipients = []): Thread;

    /**
     * New message.
     *
     * @param Thread $thread
     * @param User $user
     * @param array $content
     * @return Message
     */
    public function newMessage(Thread $thread, User $user, array $content): Message;

    /**
     * Mark as read a tread of a user.
     *
     * @param Thread $thread
     * @param string $user_id
     * @return Participant
     */
    public function markAsRead(Thread $thread, string $user_id): Participant;

    /**
     * Mark as read all messages of a user.
     *
     * @param string $user_id
     * @return bool
     */
    public function markAsReadAll(string $user_id): bool;

    /**
     * Mark as read all messages of a user.
     *
     * @param Thread $thread
     * @param User $user
     * @param bool $mark_as_read
     * @return Participant
     */
    public function addParticipant(Thread $thread, User $user, bool $mark_as_read = false): Participant;
}
