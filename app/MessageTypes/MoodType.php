<?php

namespace App\MessageTypes;

use App\Interfaces\MessageTypeInterface;

class MoodType implements MessageTypeInterface
{
    public function name(): string
    {
        return 'mood';
    }

    public function version(): string
    {
        return '1.0';
    }

    public function purpose(): string
    {
        return 'Share your mood with your lads. The mood is a closed enum drawn from a fixed vocabulary, with an optional 1-5 intensity. No free text.';
    }

    public function schema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['mood'],
            'properties' => [
                'mood' => [
                    'type' => 'string',
                    'enum' => ['happy', 'sad', 'angry', 'excited', 'tired', 'anxious', 'calm', 'bored', 'grateful', 'stressed', 'loved', 'meh'],
                ],
                'intensity' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 5],
            ],
        ];
    }

    public function rendererHint(): string
    {
        return 'MoodCard';
    }
}