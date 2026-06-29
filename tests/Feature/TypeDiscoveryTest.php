<?php

namespace Tests\Feature;

use Tests\TestCase;

class TypeDiscoveryTest extends TestCase
{
    public function test_types_endpoint_is_public_and_lists_all_types(): void
    {
        $response = $this->getJson(route('types'));

        $response->assertOk();
        $response->assertJsonCount(6);
        $response->assertJsonStructure([
            ['type', 'version', 'purpose', 'schema', 'renderer_hint'],
        ]);

        $types = collect($response->json())->pluck('type')->all();
        foreach (['currency', 'location', 'status', 'file_reference', 'metric', 'mood'] as $name) {
            $this->assertContains($name, $types);
        }
    }

    public function test_metric_exposes_compatible_unit_constraints(): void
    {
        $response = $this->getJson(route('types'));

        $metric = collect($response->json())->firstWhere('type', 'metric');

        $this->assertArrayHasKey('constraints', $metric);
        $this->assertArrayHasKey('compatible_units', $metric['constraints']);
        $this->assertSame(['celsius', 'fahrenheit', 'kelvin'], $metric['constraints']['compatible_units']['temperature']);
    }
}
