<?php

namespace Tests\Feature;

use Illuminate\Http\JsonResponse;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class TypeDiscoveryTest extends TestCase
{
    /**
     * @param  TestResponse<JsonResponse>  $response
     * @return array<int, array<string, mixed>>
     */
    private function typesJson(TestResponse $response): array
    {
        /** @var array<int, array<string, mixed>> $json */
        $json = $response->json();

        return $json;
    }

    public function test_types_endpoint_is_public_and_lists_all_types(): void
    {
        $response = $this->getJson(route('types'));

        $response->assertOk();
        $response->assertJsonCount(7);
        $response->assertJsonStructure([
            ['type', 'version', 'purpose', 'schema', 'renderer_hint'],
        ]);

        $types = collect($this->typesJson($response))->pluck('type')->all();
        foreach (['currency', 'location', 'status', 'file_reference', 'metric', 'mood', 'ping'] as $name) {
            $this->assertContains($name, $types);
        }
    }

    public function test_metric_exposes_compatible_unit_constraints(): void
    {
        $response = $this->getJson(route('types'));

        /** @var array<string, mixed> $metric */
        $metric = collect($this->typesJson($response))->firstWhere('type', 'metric');

        $this->assertArrayHasKey('constraints', $metric);

        /** @var array<string, mixed> $constraints */
        $constraints = $metric['constraints'];

        $this->assertArrayHasKey('compatible_units', $constraints);

        /** @var array<string, array<int, string>> $compatibleUnits */
        $compatibleUnits = $constraints['compatible_units'];

        $this->assertSame(['celsius', 'fahrenheit', 'kelvin'], $compatibleUnits['temperature']);
    }
}
