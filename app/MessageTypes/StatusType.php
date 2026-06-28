<?php

namespace App\MessageTypes;

use App\Interfaces\MessageTypeInterface;

class StatusType implements MessageTypeInterface
{
    public function name(): string
    {
        return 'status';
    }

    public function version(): string
    {
        return '1.0';
    }

    public function purpose(): string
    {
        return 'A named state transition with an optional reason code. Used for order status, device state, and workflow transitions. Status is a state machine, not a notes field.';
    }

    public function schema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['state'],
            'properties' => [
                'state' => ['type' => 'string', 'pattern' => '^[a-z][a-z0-9_]*$'],
                'reason' => ['type' => 'string', 'pattern' => '^[a-z][a-z0-9_]*$', 'maxLength' => 60],
            ],
        ];
    }

    public function rendererHint(): string
    {
        return 'StatusBadge';
    }
}