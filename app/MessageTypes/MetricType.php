<?php

namespace App\MessageTypes;

use App\Interfaces\CrossFieldValidatableInterface;
use App\Interfaces\MessageTypeInterface;

class MetricType implements MessageTypeInterface, CrossFieldValidatableInterface
{
    /**
     * Valid quantity → unit pairs. Any combination not listed is rejected
     * at write time. Both sides are closed controlled vocabularies — there
     * are no free-form measurement names or units.
     *
     * @var array<string, array<int, string>>
     */
    public const COMPATIBLE_UNITS = [
        'temperature'     => ['celsius', 'fahrenheit', 'kelvin'],
        'humidity'        => ['percent'],
        'pressure'        => ['hpa', 'bar'],
        'speed'           => ['m_s', 'km_h', 'mph'],
        'distance'        => ['km', 'm', 'cm', 'mm'],
        'mass'            => ['kg', 'g'],
        'energy'          => ['kwh', 'wh'],
        'power'           => ['w', 'kw'],
        'voltage'         => ['v', 'mv'],
        'current'         => ['a', 'ma'],
        'frequency'       => ['hz', 'khz', 'mhz'],
        'luminance'       => ['lux'],
        'co2'             => ['ppm'],
        'pm2_5'           => ['ug_m3'],
        'pm10'            => ['ug_m3'],
        'battery_level'   => ['percent'],
        'signal_strength' => ['dbm'],
    ];

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
        return 'A scalar numeric measurement identified by a controlled quantity kind and SI-aligned unit. Cross-field compatibility between quantity and unit is enforced at write time.';
    }

    public function schema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['quantity', 'value', 'unit'],
            'properties' => [
                'quantity' => ['type' => 'string', 'enum' => array_keys(self::COMPATIBLE_UNITS)],
                'value' => ['type' => 'number'],
                'unit' => ['type' => 'string', 'enum' => array_values(array_unique(array_merge(...array_values(self::COMPATIBLE_UNITS))))],
                'recorded_at' => ['type' => 'string', 'format' => 'date-time'],
            ],
        ];
    }

    public function validate(array $payload): array
    {
        $allowed = self::COMPATIBLE_UNITS[$payload['quantity']] ?? [];

        if (! in_array($payload['unit'], $allowed, true)) {
            return ["unit '{$payload['unit']}' is not valid for quantity '{$payload['quantity']}'"];
        }

        return [];
    }

    public function constraints(): array
    {
        return ['compatible_units' => self::COMPATIBLE_UNITS];
    }

    public function rendererHint(): string
    {
        return 'MetricDisplay';
    }
}