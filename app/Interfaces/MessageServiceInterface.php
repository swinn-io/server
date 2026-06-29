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
     * All threads that user is participating in.
     */
    public function threads(User $user): LengthAwarePaginator;

    /**
     * All threads that user is participating in, with new messages.
     *
     *
     * @throws ModelNotFoundException
     */
    public function unreadThreads(User $user): Collection;

    /**
     * Retrieve a thread.
     */
    public function thread(string $thread_id): Thread;

    /**
     * User ids that are associated with the thread.
     */
    public function threadParticipants(string $thread_id): Collection;

    /**
     * New message thread.
     */
    public function newThread(string $subject, User $user, array $content, array $recipients = []): Thread;

    /**
     * New message.
     */
    public function newMessage(Thread $thread, User $user, array $content): Message;

    /**
     * Mark as read a tread of a user.
     */
    public function markAsRead(Thread $thread, User $user): Participant;

    /**
     * Mark as read all messages of a user.
     */
    public function markAsReadAll(User $user): bool;

    /**
     * Mark as read all messages of a user.
     */
    public function addParticipant(Thread $thread, User $user, bool $mark_as_read = false): Participant;
}
