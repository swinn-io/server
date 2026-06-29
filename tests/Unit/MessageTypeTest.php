<?php

namespace Tests\Unit;

use App\MessageTypes\CurrencyType;
use App\MessageTypes\FileReferenceType;
use App\MessageTypes\LocationType;
use App\MessageTypes\MetricType;
use App\MessageTypes\MoodType;
use App\MessageTypes\StatusType;
use Opis\JsonSchema\Helper;
use Opis\JsonSchema\Validator;
use PHPUnit\Framework\TestCase;

class MessageTypeTest extends TestCase
{
    private function accepts(array $schema, array $payload): bool
    {
        return (new Validator)
            ->validate(Helper::toJSON($payload), Helper::toJSON($schema))
            ->isValid();
    }

    public function test_currency(): void
    {
        $type = new CurrencyType;
        $this->assertSame('currency', $type->name());
        $this->assertSame('1.0', $type->version());
        $this->assertSame('CurrencyCard', $type->rendererHint());
        $this->assertNotEmpty($type->purpose());

        $this->assertTrue($this->accepts($type->schema(), ['amount' => 142.5, 'currency_code' => 'USD']));
        $this->assertFalse($this->accepts($type->schema(), ['amount' => 1, 'currency_code' => 'usd']));
        $this->assertFalse($this->accepts($type->schema(), ['amount' => 1]));
        $this->assertFalse($this->accepts($type->schema(), ['amount' => 1, 'currency_code' => 'USD', 'x' => 1]));
    }

    public function test_location(): void
    {
        $type = new LocationType;
        $this->assertSame('location', $type->name());
        $this->assertTrue($this->accepts($type->schema(), ['lat' => 51.5, 'lng' => -0.12]));
        $this->assertFalse($this->accepts($type->schema(), ['lat' => 200, 'lng' => 0]));
        $this->assertFalse($this->accepts($type->schema(), ['lat' => 1]));
    }

    public function test_status(): void
    {
        $type = new StatusType;
        $this->assertSame('status', $type->name());
        $this->assertTrue($this->accepts($type->schema(), ['state' => 'dispatched', 'reason' => 'carrier_collected']));
        $this->assertTrue($this->accepts($type->schema(), ['state' => 'dispatched']));
        $this->assertFalse($this->accepts($type->schema(), ['state' => 'Dispatched']));
        $this->assertFalse($this->accepts($type->schema(), ['state' => 'ok', 'reason' => 'Free text here']));
        // Bounded slug: 60 chars is the ceiling, 61 is rejected.
        $this->assertTrue($this->accepts($type->schema(), ['state' => 'a'.str_repeat('b', 59)]));
        $this->assertFalse($this->accepts($type->schema(), ['state' => 'a'.str_repeat('b', 60)]));
    }

    public function test_file_reference(): void
    {
        $type = new FileReferenceType;
        $this->assertSame('file_reference', $type->name());
        $this->assertTrue($this->accepts($type->schema(), [
            'url' => 'https://cdn.example.com/x.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 1024,
        ]));
        $this->assertFalse($this->accepts($type->schema(), [
            'url' => 'https://x/y', 'mime_type' => 'application/pdf', 'size_bytes' => 1024, 'name' => 'x.pdf',
        ]));
        $this->assertFalse($this->accepts($type->schema(), [
            'url' => 'https://x/y', 'mime_type' => 'NOT A MIME', 'size_bytes' => 1024,
        ]));
    }

    public function test_metric(): void
    {
        $type = new MetricType;
        $this->assertSame('metric', $type->name());
        $this->assertTrue($this->accepts($type->schema(), [
            'quantity' => 'temperature', 'value' => 22.4, 'unit' => 'celsius',
        ]));
        $this->assertTrue($this->accepts($type->schema(), [
            'quantity' => 'temperature', 'value' => 22.4, 'unit' => 'celsius', 'recorded_at' => '2026-06-27T14:30:00Z',
        ]));
        // quantity and unit are closed enums — anything outside them fails the schema.
        $this->assertFalse($this->accepts($type->schema(), ['quantity' => 'Temp', 'value' => 1, 'unit' => 'celsius']));
        $this->assertFalse($this->accepts($type->schema(), ['quantity' => 'temperature', 'value' => 1, 'unit' => 'parsec']));
        // No free-form name field anymore.
        $this->assertFalse($this->accepts($type->schema(), ['name' => 'temp', 'value' => 1, 'unit' => 'celsius']));
    }

    public function test_metric_cross_field_compatibility(): void
    {
        $type = new MetricType;
        // Compatible quantity/unit pair → no violations.
        $this->assertSame([], $type->validate(['quantity' => 'temperature', 'value' => 22.4, 'unit' => 'celsius']));
        // A real unit paired with the wrong quantity → violation.
        $violations = $type->validate(['quantity' => 'temperature', 'value' => 1, 'unit' => 'dbm']);
        $this->assertNotEmpty($violations);
        $this->assertStringContainsString("not valid for quantity 'temperature'", $violations[0]);
        // The compatibility matrix is exposed for discovery.
        $this->assertSame(['compatible_units' => MetricType::COMPATIBLE_UNITS], $type->constraints());
    }

    public function test_mood(): void
    {
        $type = new MoodType;
        $this->assertSame('mood', $type->name());
        $this->assertSame('MoodCard', $type->rendererHint());
        $this->assertTrue($this->accepts($type->schema(), ['mood' => 'happy']));
        $this->assertTrue($this->accepts($type->schema(), ['mood' => 'stressed', 'intensity' => 4]));
        $this->assertFalse($this->accepts($type->schema(), ['mood' => 'ecstatic']));
        $this->assertFalse($this->accepts($type->schema(), ['mood' => 'happy', 'intensity' => 9]));
        $this->assertFalse($this->accepts($type->schema(), ['mood' => 'happy', 'note' => 'feeling great']));
    }
}
