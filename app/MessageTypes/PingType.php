<?php

namespace App\MessageTypes;

use App\Interfaces\MessageTypeInterface;

class PingType implements MessageTypeInterface
{
    public function name(): string
    {
        return 'ping';
    }

    public function version(): string
    {
        return '1.0';
    }

    public function purpose(): string
    {
        return 'Nudge specific participants of a thread. The payload lists the pinged user IDs (a targeted mention) so clients may highlight the message for those users.';
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['user_ids'],
            'properties' => [
                'user_ids' => [
                    'type' => 'array',
                    'minItems' => 1,
                    'uniqueItems' => true,
                    'items' => ['type' => 'string'],
                ],
            ],
        ];
    }

    public function rendererHint(): string
    {
        return 'PingCard';
    }
}
