<?php

namespace App\MessageTypes;

use App\Interfaces\MessageTypeInterface;

class MetricType implements MessageTypeInterface
{
    public function name(): string
    {
        return 'metric';
    }

    public function version(): string
    {
        return '1.0';
    }

    public function purpose(): string
    {
        return 'A named numeric measurement with a unit. Used for sensor readings, KPIs, and any scalar value at a point in time.';
    }

    public function schema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['name', 'value', 'unit'],
            'properties' => [
                'name' => ['type' => 'string', 'pattern' => '^[a-z][a-z0-9_]*$'],
                'value' => ['type' => 'number'],
                'unit' => ['type' => 'string', 'pattern' => '^[a-z][a-z0-9_]*$'],
                'recorded_at' => ['type' => 'string', 'format' => 'date-time'],
            ],
        ];
    }

    public function rendererHint(): string
    {
        return 'MetricDisplay';
    }
}