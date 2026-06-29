<?php

namespace App\MessageTypes;

use App\Interfaces\MessageTypeInterface;

class LocationType implements MessageTypeInterface
{
    public function name(): string
    {
        return 'location';
    }

    public function version(): string
    {
        return '1.0';
    }

    public function purpose(): string
    {
        return 'A geographic coordinate. Used for delivery tracking, check-ins, and asset location.';
    }

    public function schema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['lat', 'lng'],
            'properties' => [
                'lat' => ['type' => 'number', 'minimum' => -90, 'maximum' => 90],
                'lng' => ['type' => 'number', 'minimum' => -180, 'maximum' => 180],
            ],
        ];
    }

    public function rendererHint(): string
    {
        return 'LocationPin';
    }
}
