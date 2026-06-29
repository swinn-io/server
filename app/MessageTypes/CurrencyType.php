<?php

namespace App\MessageTypes;

use App\Interfaces\MessageTypeInterface;

class CurrencyType implements MessageTypeInterface
{
    public function name(): string
    {
        return 'currency';
    }

    public function version(): string
    {
        return '1.0';
    }

    public function purpose(): string
    {
        return 'A monetary value in a specific currency. Used for payment references, invoice notifications, and balance updates.';
    }

    public function schema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['amount', 'currency_code'],
            'properties' => [
                'amount' => ['type' => 'number'],
                'currency_code' => ['type' => 'string', 'pattern' => '^[A-Z]{3}$'],
            ],
        ];
    }

    public function rendererHint(): string
    {
        return 'CurrencyCard';
    }
}