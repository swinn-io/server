<?php

namespace App\Console\Commands;

use App\Exceptions\InvalidEnvelopeException;
use App\Interfaces\MessageServiceInterface;
use App\Models\Thread;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Http\JsonResponse;

class PingThreadUsers extends Command
{
    /**
     * @var string
     */
    protected $signature = 'thread:ping
        {--thread= : Existing thread UUID to ping into (omit to create a new thread)}
        {--from= : Sender / thread-creator user UUID}
        {--user=* : User UUID(s) to ping}
        {--subject= : Subject for a NEW thread (required when --thread is omitted)}
        {--note= : Optional short note included in the ping payload}';

    /**
     * @var string
     */
    protected $description = 'Ping certain users in an existing thread, or create a new thread whose opening message is the ping.';

    public function handle(MessageServiceInterface $service): int
    {
        $threadId = $this->option('thread');
        $subject = $this->option('subject');
        $fromId = $this->option('from');
        /** @var array<int, string> $userIds */
        $userIds = array_values(array_unique($this->option('user')));
        $note = $this->option('note');

        if (! is_string($fromId) || $fromId === '') {
            $this->error('The --from option (sender user UUID) is required.');

            return self::FAILURE;
        }

        if ($userIds === []) {
            $this->error('Provide at least one --user to ping.');

            return self::FAILURE;
        }

        $sender = User::find($fromId);
        if ($sender === null) {
            $this->error("Sender user {$fromId} not found.");

            return self::FAILURE;
        }

        /** @var \Illuminate\Database\Eloquent\Collection<int, User> $users */
        $users = User::findMany($userIds);
        if ($users->count() !== count($userIds)) {
            $found = $users->pluck('id')->all();
            $missing = array_diff($userIds, $found);
            $this->error('These users were not found: '.implode(', ', $missing));

            return self::FAILURE;
        }

        $envelope = [
            'type' => 'ping',
            'version' => '1.0',
            'payload' => array_filter(
                ['user_ids' => $userIds, 'note' => $note],
                fn ($value) => $value !== null && $value !== '',
            ),
        ];

        if ($threadId === null) {
            try {
                $thread = $service->newThread((string) $subject, $sender, $envelope, $userIds);
            } catch (InvalidEnvelopeException $e) {
                $this->error('Could not send ping: '.$this->renderEnvelopeError($e));

                return self::FAILURE;
            }

            $this->info("Created thread {$thread->id} and pinged {$users->count()} user(s).");

            return self::SUCCESS;
        }

        $thread = Thread::find($threadId);
        if ($thread === null) {
            $this->error("Thread {$threadId} not found.");

            return self::FAILURE;
        }

        $participantIds = $thread->users()->get()->pluck('id')->all();

        if (! in_array($sender->id, $participantIds, true)) {
            $this->error("Sender {$sender->id} is not a participant of thread {$thread->id}.");

            return self::FAILURE;
        }

        $notParticipants = array_diff($users->pluck('id')->all(), $participantIds);
        if ($notParticipants !== []) {
            $this->error('These users are not in the thread: '.implode(', ', $notParticipants));

            return self::FAILURE;
        }

        try {
            $message = $service->newMessage($thread, $sender, $envelope);
        } catch (InvalidEnvelopeException $e) {
            $this->error('Could not send ping: '.$this->renderEnvelopeError($e));

            return self::FAILURE;
        }

        $this->info("Pinged {$users->count()} user(s) in thread {$thread->id} (message {$message->id}).");

        return self::SUCCESS;
    }

    private function renderEnvelopeError(InvalidEnvelopeException $e): string
    {
        $response = $e->getResponse();

        return $response instanceof JsonResponse
            ? (string) $response->getContent()
            : $e->getMessage();
    }
}
