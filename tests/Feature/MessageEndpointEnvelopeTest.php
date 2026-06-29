<?php

namespace Tests\Feature;

use App\Models\Thread;
use App\Models\User;
use Tests\TestCase;

class MessageEndpointEnvelopeTest extends TestCase
{
    private function validBody(): array
    {
        return ['type' => 'mood', 'version' => '1.0', 'payload' => ['mood' => 'happy']];
    }

    public function testStoreRejectsFreeTextWith422(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'api')->postJson(route('message.store'), [
            'subject' => 'Hi',
            'content' => ['just' => 'text'],
        ]);

        $response->assertStatus(422);
        $response->assertJson(['error' => 'invalid_envelope']);
    }

    public function testStoreAcceptsValidEnvelope(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'api')->postJson(route('message.store'), [
            'subject' => 'Hi',
            'content' => $this->validBody(),
        ]);

        $response->assertCreated();
    }

    public function testAppendRejectsInvalidPayloadWith422(): void
    {
        $user = User::factory()->create();
        $thread = app(\App\Interfaces\MessageServiceInterface::class)
            ->newThread('Hi', $user, $this->validBody());

        $response = $this->actingAs($user, 'api')->postJson(route('message.new', ['id' => $thread->id]), [
            'body' => ['type' => 'mood', 'version' => '1.0', 'payload' => ['mood' => 'ecstatic']],
        ]);

        $response->assertStatus(422);
        $response->assertJson(['error' => 'invalid_payload']);
    }
}