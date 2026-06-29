<?php

namespace Tests\Unit;

use App\Services\TypeRegistry;
use Tests\TestCase;

class MessageTypeRegistrationTest extends TestCase
{
    public function testRegistryResolvesWithAllSixTypes(): void
    {
        $registry = app(TypeRegistry::class);
        $this->assertCount(6, $registry->all());
        foreach (['currency', 'location', 'status', 'file_reference', 'metric', 'mood'] as $name) {
            $this->assertTrue($registry->has($name), "missing type {$name}");
        }
    }
}