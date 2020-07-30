<?php

namespace App\Interfaces;

use App\Models\Message;
use App\Models\Thread;
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
     * @param string $user_id
     * @param array $content
     * @param null|array $recipients
     * @return Thread
     */
    public function newThread(string $subject, string $user_id, array $content, ?array $recipients): Thread;

    /**
     * New message.
     *
     * @param string $thread_id
     * @param string $user_id
     * @param array $content
     * @return Message
     */
    public function newMessage(string $thread_id, string $user_id, array $content): Message;

    /**
     * Mark as read all messages of a user.
     *
     * @param Thread $thread
     * @param string $user_id
     */
    public function markAsRead(Thread $thread, string $user_id): void;

    /**
     * Mark as read all messages of a user.
     *
     * @param Thread $thread
     * @param string $user_id
     */
    public function addParticipant(Thread $thread, string $user_id): void;
}
