<?php

namespace Tests\Feature;

use Tests\TestCase;

class TypeDiscoveryTest extends TestCase
{
    public function testTypesEndpointIsPublicAndListsAllTypes(): void
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
}