<?php

namespace Tests\Feature;

use App\Exceptions\InvalidEnvelopeException;
use App\Interfaces\MessageServiceInterface;
use App\Models\Thread;
use App\Models\User;
use Tests\TestCase;

class MessageEnvelopeTest extends TestCase
{
    private MessageServiceInterface $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(MessageServiceInterface::class);
    }

    private function validBody(): array
    {
        return ['type' => 'mood', 'version' => '1.0', 'payload' => ['mood' => 'happy']];
    }

    public function testNewThreadAcceptsValidEnvelope(): void
    {
        $user = User::factory()->create();
        $thread = $this->service->newThread('Subject', $user, $this->validBody());
        $this->assertCount(1, $thread->messages);
    }

    public function testNewThreadRejectsFreeText(): void
    {
        $user = User::factory()->create();
        $this->expectException(InvalidEnvelopeException::class);
        $this->service->newThread('Subject', $user, ['some' => 'data']);
    }

    public function testNewThreadDoesNotCreateOrphanThreadOnInvalidBody(): void
    {
        $user = User::factory()->create();
        $before = Thread::count();
        try {
            $this->service->newThread('Subject', $user, ['some' => 'data']);
        } catch (InvalidEnvelopeException $e) {
            // expected
        }
        $this->assertSame($before, Thread::count());
    }

    public function testNewMessageRejectsInvalidPayload(): void
    {
        $user = User::factory()->create();
        $thread = $this->service->newThread('Subject', $user, $this->validBody());
        $this->expectException(InvalidEnvelopeException::class);
        $this->service->newMessage($thread, $user, [
            'type' => 'mood', 'version' => '1.0', 'payload' => ['mood' => 'ecstatic'],
        ]);
    }
}