<?php

namespace Tests\Unit;

use App\MessageTypes\CurrencyType;
use App\MessageTypes\MetricType;
use App\MessageTypes\MoodType;
use App\Services\TypeRegistry;
use PHPUnit\Framework\TestCase;

class TypeRegistryTest extends TestCase
{
    private function registry(): TypeRegistry
    {
        return new TypeRegistry([new CurrencyType, new MoodType]);
    }

    /**
     * @param  array<string, mixed>  $envelope
     * @return array<string, mixed>
     */
    private function invalid(TypeRegistry $registry, array $envelope): array
    {
        $error = $registry->validate($envelope);
        $this->assertNotNull($error);

        return $error;
    }

    public function test_has_and_get(): void
    {
        $registry = $this->registry();
        $this->assertTrue($registry->has('currency'));
        $this->assertFalse($registry->has('nope'));
        $this->assertInstanceOf(CurrencyType::class, $registry->get('currency'));
        $this->assertCount(2, $registry->all());
    }

    public function test_valid_envelope_returns_null(): void
    {
        $error = $this->registry()->validate([
            'type' => 'currency', 'version' => '1.0',
            'payload' => ['amount' => 10, 'currency_code' => 'USD'],
        ]);
        $this->assertNull($error);
    }

    public function test_missing_keys_rejected(): void
    {
        $this->assertSame('invalid_envelope', $this->invalid($this->registry(), ['type' => 'currency'])['error']);
        $this->assertSame('invalid_envelope', $this->invalid($this->registry(), [
            'type' => 'currency', 'version' => '1.0', 'payload' => ['x' => 1], 'extra' => 1,
        ])['error']);
    }

    public function test_non_object_payload_rejected(): void
    {
        $this->assertSame('invalid_envelope', $this->invalid($this->registry(), [
            'type' => 'currency', 'version' => '1.0', 'payload' => 'nope',
        ])['error']);
    }

    public function test_unknown_type(): void
    {
        $error = $this->invalid($this->registry(), [
            'type' => 'weather', 'version' => '1.0', 'payload' => ['x' => 1],
        ]);
        $this->assertSame('unknown_type', $error['error']);
        $this->assertSame('weather', $error['type']);
    }

    public function test_unknown_version(): void
    {
        $error = $this->invalid($this->registry(), [
            'type' => 'currency', 'version' => '9.9', 'payload' => ['amount' => 1, 'currency_code' => 'USD'],
        ]);
        $this->assertSame('unknown_version', $error['error']);
    }

    public function test_invalid_payload(): void
    {
        $error = $this->invalid($this->registry(), [
            'type' => 'currency', 'version' => '1.0', 'payload' => ['amount' => 1, 'currency_code' => 'usd'],
        ]);
        $this->assertSame('invalid_payload', $error['error']);
        $this->assertNotEmpty($error['violations']);
    }

    public function test_cross_field_violation_rejected(): void
    {
        $registry = new TypeRegistry([new MetricType]);

        // Schema-valid (both are members of their enums) but the pair is incompatible.
        $error = $this->invalid($registry, [
            'type' => 'metric', 'version' => '1.0',
            'payload' => ['quantity' => 'temperature', 'value' => 1, 'unit' => 'dbm'],
        ]);

        $this->assertSame('invalid_payload', $error['error']);

        /** @var array<int, string> $violations */
        $violations = $error['violations'];

        $this->assertStringContainsString("not valid for quantity 'temperature'", $violations[0]);

        // A compatible pair passes.
        $this->assertNull($registry->validate([
            'type' => 'metric', 'version' => '1.0',
            'payload' => ['quantity' => 'temperature', 'value' => 22.4, 'unit' => 'celsius'],
        ]));
    }
}
