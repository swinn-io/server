<?php

namespace Tests\Feature;

use App\Interfaces\MessageServiceInterface;
use App\Models\User;
use Tests\TestCase;

class MessageEndpointEnvelopeTest extends TestCase
{
    private function validBody(): array
    {
        return ['type' => 'mood', 'version' => '1.0', 'payload' => ['mood' => 'happy']];
    }

    public function test_store_rejects_free_text_with422(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'api')->postJson(route('message.store'), [
            'subject' => 'Hi',
            'content' => ['just' => 'text'],
        ]);

        $response->assertStatus(422);
        $response->assertJson(['error' => 'invalid_envelope']);
    }

    public function test_store_accepts_valid_envelope(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'api')->postJson(route('message.store'), [
            'subject' => 'Hi',
            'content' => $this->validBody(),
        ]);

        $response->assertCreated();
    }

    public function test_append_rejects_invalid_payload_with422(): void
    {
        $user = User::factory()->create();
        $thread = app(MessageServiceInterface::class)
            ->newThread('Hi', $user, $this->validBody());

        $response = $this->actingAs($user, 'api')->postJson(route('message.new', ['id' => $thread->id]), [
            'body' => ['type' => 'mood', 'version' => '1.0', 'payload' => ['mood' => 'ecstatic']],
        ]);

        $response->assertStatus(422);
        $response->assertJson(['error' => 'invalid_payload']);
    }
}
