# Typed-Payload Protocol Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Enforce that every message body is a typed, schema-validated JSON envelope drawn from a closed registry of six types, and expose the registry at `GET /types`.

**Architecture:** A code-defined `TypeRegistry` holds one class per message type, each declaring a JSON Schema. `MessageService` validates every write against the registry (so HTTP endpoints, seeders, and internal callers are all covered) and throws a `422`-rendering exception on failure. Schemas are maximally strict — `additionalProperties:false`, no free-text strings. An unauthenticated `GET /types` publishes the registry for client discovery.

**Tech Stack:** Laravel 13, PHP 8.3, `opis/json-schema` (validator), PHPUnit, MySQL (`swinn-test`).

---

## Conventions for the engineer (read first)

- **PHP binary:** use `/usr/local/opt/php@8.3/bin/php` for everything. The bare `php` is 8.5 and is not the target.
- **Composer:** `/usr/local/opt/php@8.3/bin/php /usr/local/bin/composer.phar <cmd>`.
- **Run the whole test suite:** `/usr/local/opt/php@8.3/bin/php vendor/bin/phpunit`
- **Run one test:** `/usr/local/opt/php@8.3/bin/php vendor/bin/phpunit --filter testName`
- **Tests use real MySQL `swinn-test`** (see `phpunit.xml`) with `DatabaseMigrations`. The DB must be reachable at `127.0.0.1:3306` root/root.
- **Contracts** live in `app/Interfaces` with the `*Interface` suffix (project convention). **Services** in `app/Services`. **Project providers** in `app/Providers/Project`.
- **Branch:** already on `feat/typed-payload-protocol`. Commit after every task. **Do not push or open a PR until all tasks pass** — that's the final step.
- **The envelope** is always exactly `{ "type": string, "version": string, "payload": object }`.
- **opis/json-schema needs decoded-JSON values** (`stdClass`, not PHP assoc arrays). Always normalize with `Opis\JsonSchema\Helper::toJSON($value)` before validating, for both schema and payload. This is the single most common mistake — assoc arrays are treated as JSON arrays, not objects.

---

## Task 1: Install the JSON Schema validator

**Files:**
- Modify: `composer.json`, `composer.lock` (via composer)

**Step 1: Require the package**

Run: `/usr/local/opt/php@8.3/bin/php /usr/local/bin/composer.phar require opis/json-schema`
Expected: adds `opis/json-schema` (^2) to `require`, updates lock, no errors.

**Step 2: Verify it loads**

Run: `/usr/local/opt/php@8.3/bin/php -r 'require "vendor/autoload.php"; var_dump(class_exists(Opis\JsonSchema\Validator::class), method_exists(Opis\JsonSchema\Helper::class, "toJSON"));'`
Expected: `bool(true)` twice.

**Step 3: Commit**

```bash
git add composer.json composer.lock
git commit -m "build: add opis/json-schema for payload validation"
```

---

## Task 2: The `MessageTypeInterface` contract

**Files:**
- Create: `app/Interfaces/MessageTypeInterface.php`

**Step 1: Write the interface**

```php
<?php

namespace App\Interfaces;

interface MessageTypeInterface
{
    /** Unique snake_case slug, e.g. "currency". */
    public function name(): string;

    /** Semver string, e.g. "1.0". */
    public function version(): string;

    /** One-paragraph plain-English description for the /types endpoint. */
    public function purpose(): string;

    /** JSON Schema array used for write-time payload validation. */
    public function schema(): array;

    /** Component name hint for human clients, e.g. "CurrencyCard". */
    public function rendererHint(): string;
}
```

**Step 2: Verify it parses**

Run: `/usr/local/opt/php@8.3/bin/php -l app/Interfaces/MessageTypeInterface.php`
Expected: `No syntax errors detected`.

**Step 3: Commit**

```bash
git add app/Interfaces/MessageTypeInterface.php
git commit -m "feat: add MessageTypeInterface contract"
```

---

## Task 3: The six message types (TDD)

Each type is small and declarative. Write one unit test that asserts the type's identity fields and that its schema accepts a known-good payload and rejects a known-bad one, then implement the class. Group the six into a single test file but commit once at the end of the task.

**Files:**
- Create: `app/MessageTypes/CurrencyType.php`
- Create: `app/MessageTypes/LocationType.php`
- Create: `app/MessageTypes/StatusType.php`
- Create: `app/MessageTypes/FileReferenceType.php`
- Create: `app/MessageTypes/MetricType.php`
- Create: `app/MessageTypes/MoodType.php`
- Test: `tests/Unit/MessageTypeTest.php`

**Step 1: Write the failing test**

```php
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
        return (new Validator())
            ->validate(Helper::toJSON($payload), Helper::toJSON($schema))
            ->isValid();
    }

    public function testCurrency(): void
    {
        $type = new CurrencyType();
        $this->assertSame('currency', $type->name());
        $this->assertSame('1.0', $type->version());
        $this->assertSame('CurrencyCard', $type->rendererHint());
        $this->assertNotEmpty($type->purpose());

        $this->assertTrue($this->accepts($type->schema(), ['amount' => 142.5, 'currency_code' => 'USD']));
        $this->assertFalse($this->accepts($type->schema(), ['amount' => 1, 'currency_code' => 'usd'])); // bad pattern
        $this->assertFalse($this->accepts($type->schema(), ['amount' => 1])); // missing required
        $this->assertFalse($this->accepts($type->schema(), ['amount' => 1, 'currency_code' => 'USD', 'x' => 1])); // additional
    }

    public function testLocation(): void
    {
        $type = new LocationType();
        $this->assertSame('location', $type->name());
        $this->assertTrue($this->accepts($type->schema(), ['lat' => 51.5, 'lng' => -0.12]));
        $this->assertFalse($this->accepts($type->schema(), ['lat' => 200, 'lng' => 0])); // out of range
        $this->assertFalse($this->accepts($type->schema(), ['lat' => 1])); // missing lng
    }

    public function testStatus(): void
    {
        $type = new StatusType();
        $this->assertSame('status', $type->name());
        $this->assertTrue($this->accepts($type->schema(), ['state' => 'dispatched', 'reason' => 'carrier_collected']));
        $this->assertTrue($this->accepts($type->schema(), ['state' => 'dispatched'])); // reason optional
        $this->assertFalse($this->accepts($type->schema(), ['state' => 'Dispatched'])); // bad pattern
        $this->assertFalse($this->accepts($type->schema(), ['state' => 'ok', 'reason' => 'Free text here'])); // bad pattern
    }

    public function testFileReference(): void
    {
        $type = new FileReferenceType();
        $this->assertSame('file_reference', $type->name());
        $this->assertTrue($this->accepts($type->schema(), [
            'url' => 'https://cdn.example.com/x.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 1024,
        ]));
        $this->assertFalse($this->accepts($type->schema(), [
            'url' => 'https://x/y', 'mime_type' => 'application/pdf', 'size_bytes' => 1024, 'name' => 'x.pdf',
        ])); // name no longer allowed
        $this->assertFalse($this->accepts($type->schema(), [
            'url' => 'https://x/y', 'mime_type' => 'NOT A MIME', 'size_bytes' => 1024,
        ])); // bad mime pattern
    }

    public function testMetric(): void
    {
        $type = new MetricType();
        $this->assertSame('metric', $type->name());
        $this->assertTrue($this->accepts($type->schema(), [
            'name' => 'ambient_temperature', 'value' => 22.4, 'unit' => 'celsius',
        ]));
        $this->assertTrue($this->accepts($type->schema(), [
            'name' => 'ambient_temperature', 'value' => 22.4, 'unit' => 'celsius', 'recorded_at' => '2026-06-27T14:30:00Z',
        ]));
        $this->assertFalse($this->accepts($type->schema(), ['name' => 'Temp', 'value' => 1, 'unit' => 'c'])); // bad name pattern
    }

    public function testMood(): void
    {
        $type = new MoodType();
        $this->assertSame('mood', $type->name());
        $this->assertSame('MoodCard', $type->rendererHint());
        $this->assertTrue($this->accepts($type->schema(), ['mood' => 'happy']));
        $this->assertTrue($this->accepts($type->schema(), ['mood' => 'stressed', 'intensity' => 4]));
        $this->assertFalse($this->accepts($type->schema(), ['mood' => 'ecstatic'])); // not in enum
        $this->assertFalse($this->accepts($type->schema(), ['mood' => 'happy', 'intensity' => 9])); // out of range
        $this->assertFalse($this->accepts($type->schema(), ['mood' => 'happy', 'note' => 'feeling great'])); // additional
    }
}
```

**Step 2: Run to verify it fails**

Run: `/usr/local/opt/php@8.3/bin/php vendor/bin/phpunit --filter MessageTypeTest`
Expected: FAIL — `Class "App\MessageTypes\CurrencyType" not found`.

**Step 3: Implement the six type classes**

`app/MessageTypes/CurrencyType.php`:

```php
<?php

namespace App\MessageTypes;

use App\Interfaces\MessageTypeInterface;

class CurrencyType implements MessageTypeInterface
{
    public function name(): string { return 'currency'; }
    public function version(): string { return '1.0'; }

    public function purpose(): string
    {
        return 'A monetary value in a specific currency. Used for payment references, '
            .'invoice notifications, and balance updates.';
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

    public function rendererHint(): string { return 'CurrencyCard'; }
}
```

`app/MessageTypes/LocationType.php`:

```php
<?php

namespace App\MessageTypes;

use App\Interfaces\MessageTypeInterface;

class LocationType implements MessageTypeInterface
{
    public function name(): string { return 'location'; }
    public function version(): string { return '1.0'; }

    public function purpose(): string
    {
        return 'A geographic coordinate. Used for delivery tracking, check-ins, and asset location.';
    }

    public function schema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['lat', 'lng'],
            'properties' => [
                'lat' => ['type' => 'number', 'minimum' => -90, 'maximum' => 90],
                'lng' => ['type' => 'number', 'minimum' => -180, 'maximum' => 180],
            ],
        ];
    }

    public function rendererHint(): string { return 'LocationPin'; }
}
```

`app/MessageTypes/StatusType.php`:

```php
<?php

namespace App\MessageTypes;

use App\Interfaces\MessageTypeInterface;

class StatusType implements MessageTypeInterface
{
    public function name(): string { return 'status'; }
    public function version(): string { return '1.0'; }

    public function purpose(): string
    {
        return 'A named state transition with an optional reason code. Used for order status, '
            .'device state, and workflow transitions. Status is a state machine, not a notes field.';
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

    public function rendererHint(): string { return 'StatusBadge'; }
}
```

`app/MessageTypes/FileReferenceType.php`:

```php
<?php

namespace App\MessageTypes;

use App\Interfaces\MessageTypeInterface;

class FileReferenceType implements MessageTypeInterface
{
    public function name(): string { return 'file_reference'; }
    public function version(): string { return '1.0'; }

    public function purpose(): string
    {
        return 'A pointer to an external file: URL, MIME type, and byte size. Swinn carries the '
            .'reference only, never the file content.';
    }

    public function schema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['url', 'mime_type', 'size_bytes'],
            'properties' => [
                'url' => ['type' => 'string', 'format' => 'uri'],
                'mime_type' => ['type' => 'string', 'pattern' => '^[a-z]+/[a-z0-9.+-]+$'],
                'size_bytes' => ['type' => 'integer', 'minimum' => 1],
            ],
        ];
    }

    public function rendererHint(): string { return 'FileCard'; }
}
```

`app/MessageTypes/MetricType.php`:

```php
<?php

namespace App\MessageTypes;

use App\Interfaces\MessageTypeInterface;

class MetricType implements MessageTypeInterface
{
    public function name(): string { return 'metric'; }
    public function version(): string { return '1.0'; }

    public function purpose(): string
    {
        return 'A named numeric measurement with a unit. Used for sensor readings, KPIs, and any '
            .'scalar value at a point in time.';
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

    public function rendererHint(): string { return 'MetricDisplay'; }
}
```

`app/MessageTypes/MoodType.php`:

```php
<?php

namespace App\MessageTypes;

use App\Interfaces\MessageTypeInterface;

class MoodType implements MessageTypeInterface
{
    public function name(): string { return 'mood'; }
    public function version(): string { return '1.0'; }

    public function purpose(): string
    {
        return 'Share your mood with your lads. The mood is a closed enum drawn from a fixed '
            .'vocabulary, with an optional 1-5 intensity. No free text.';
    }

    public function schema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['mood'],
            'properties' => [
                'mood' => [
                    'type' => 'string',
                    'enum' => [
                        'happy', 'sad', 'angry', 'excited', 'tired', 'anxious',
                        'calm', 'bored', 'grateful', 'stressed', 'loved', 'meh',
                    ],
                ],
                'intensity' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 5],
            ],
        ];
    }

    public function rendererHint(): string { return 'MoodCard'; }
}
```

**Step 4: Run to verify it passes**

Run: `/usr/local/opt/php@8.3/bin/php vendor/bin/phpunit --filter MessageTypeTest`
Expected: PASS (6 tests).

**Step 5: Commit**

```bash
git add app/MessageTypes tests/Unit/MessageTypeTest.php
git commit -m "feat: add six typed-message classes with strict JSON Schemas"
```

---

## Task 4: The `TypeRegistry` (TDD)

The registry holds the types keyed by name and validates a full envelope, returning `null` on success or an error descriptor array.

**Files:**
- Create: `app/Services/TypeRegistry.php`
- Test: `tests/Unit/TypeRegistryTest.php`

**Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit;

use App\MessageTypes\CurrencyType;
use App\MessageTypes\MoodType;
use App\Services\TypeRegistry;
use PHPUnit\Framework\TestCase;

class TypeRegistryTest extends TestCase
{
    private function registry(): TypeRegistry
    {
        return new TypeRegistry([new CurrencyType(), new MoodType()]);
    }

    public function testHasAndGet(): void
    {
        $registry = $this->registry();
        $this->assertTrue($registry->has('currency'));
        $this->assertFalse($registry->has('nope'));
        $this->assertInstanceOf(CurrencyType::class, $registry->get('currency'));
        $this->assertCount(2, $registry->all());
    }

    public function testValidEnvelopeReturnsNull(): void
    {
        $error = $this->registry()->validate([
            'type' => 'currency', 'version' => '1.0',
            'payload' => ['amount' => 10, 'currency_code' => 'USD'],
        ]);
        $this->assertNull($error);
    }

    public function testMissingKeysRejected(): void
    {
        $this->assertSame('invalid_envelope', $this->registry()->validate(['type' => 'currency'])['error']);
        $this->assertSame('invalid_envelope', $this->registry()->validate([
            'type' => 'currency', 'version' => '1.0', 'payload' => ['x' => 1], 'extra' => 1,
        ])['error']);
    }

    public function testNonObjectPayloadRejected(): void
    {
        $this->assertSame('invalid_envelope', $this->registry()->validate([
            'type' => 'currency', 'version' => '1.0', 'payload' => 'nope',
        ])['error']);
    }

    public function testUnknownType(): void
    {
        $error = $this->registry()->validate([
            'type' => 'weather', 'version' => '1.0', 'payload' => ['x' => 1],
        ]);
        $this->assertSame('unknown_type', $error['error']);
        $this->assertSame('weather', $error['type']);
    }

    public function testUnknownVersion(): void
    {
        $error = $this->registry()->validate([
            'type' => 'currency', 'version' => '9.9', 'payload' => ['amount' => 1, 'currency_code' => 'USD'],
        ]);
        $this->assertSame('unknown_version', $error['error']);
    }

    public function testInvalidPayload(): void
    {
        $error = $this->registry()->validate([
            'type' => 'currency', 'version' => '1.0', 'payload' => ['amount' => 1, 'currency_code' => 'usd'],
        ]);
        $this->assertSame('invalid_payload', $error['error']);
        $this->assertNotEmpty($error['violations']);
    }
}
```

**Step 2: Run to verify it fails**

Run: `/usr/local/opt/php@8.3/bin/php vendor/bin/phpunit --filter TypeRegistryTest`
Expected: FAIL — `Class "App\Services\TypeRegistry" not found`.

**Step 3: Implement the registry**

`app/Services/TypeRegistry.php`:

```php
<?php

namespace App\Services;

use App\Interfaces\MessageTypeInterface;
use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Helper;
use Opis\JsonSchema\Validator;

class TypeRegistry
{
    /** @var array<string, MessageTypeInterface> */
    private array $types = [];

    /** @param iterable<MessageTypeInterface> $types */
    public function __construct(iterable $types = [])
    {
        foreach ($types as $type) {
            $this->types[$type->name()] = $type;
        }
    }

    /** @return array<string, MessageTypeInterface> */
    public function all(): array
    {
        return $this->types;
    }

    public function has(string $name): bool
    {
        return isset($this->types[$name]);
    }

    public function get(string $name): ?MessageTypeInterface
    {
        return $this->types[$name] ?? null;
    }

    /**
     * Validate a full envelope. Returns null on success, or an error descriptor.
     *
     * @return array<string, mixed>|null
     */
    public function validate(mixed $envelope): ?array
    {
        if (! is_array($envelope)
            || array_keys($envelope) === array_keys(array_keys($envelope)) // sequential => JSON array, not object
        ) {
            return ['error' => 'invalid_envelope', 'message' => 'Envelope must be an object.'];
        }

        $keys = array_keys($envelope);
        sort($keys);
        if ($keys !== ['payload', 'type', 'version']) {
            return ['error' => 'invalid_envelope', 'message' => 'Envelope must contain exactly type, version, and payload.'];
        }

        if (! is_string($envelope['type']) || ! is_string($envelope['version'])) {
            return ['error' => 'invalid_envelope', 'message' => 'type and version must be strings.'];
        }

        if (! is_array($envelope['payload'])) {
            return ['error' => 'invalid_envelope', 'message' => 'payload must be an object.'];
        }

        if (! $this->has($envelope['type'])) {
            return ['error' => 'unknown_type', 'type' => $envelope['type']];
        }

        $type = $this->get($envelope['type']);

        if ($envelope['version'] !== $type->version()) {
            return ['error' => 'unknown_version', 'type' => $type->name(), 'version' => $envelope['version']];
        }

        $result = (new Validator())->validate(
            Helper::toJSON($envelope['payload']),
            Helper::toJSON($type->schema()),
        );

        if (! $result->isValid()) {
            return [
                'error' => 'invalid_payload',
                'violations' => (new ErrorFormatter())->format($result->error()),
            ];
        }

        return null;
    }
}
```

Note on the object check: an empty payload `[]` and a JSON list both serialize as arrays. `Helper::toJSON` will turn an assoc array into a `stdClass` (object) and a list into a JSON array, so the schema's `'type' => 'object'` catches lists. The explicit `array_keys` guard at the top only rejects a top-level envelope that is itself a list.

**Step 4: Run to verify it passes**

Run: `/usr/local/opt/php@8.3/bin/php vendor/bin/phpunit --filter TypeRegistryTest`
Expected: PASS.

**Step 5: Commit**

```bash
git add app/Services/TypeRegistry.php tests/Unit/TypeRegistryTest.php
git commit -m "feat: add TypeRegistry with envelope validation"
```

---

## Task 5: Register the registry as a singleton

Bind a fully-populated `TypeRegistry` with all six types in the container.

**Files:**
- Create: `app/Providers/Project/MessageTypeServiceProvider.php`
- Modify: `config/app.php` (providers array, after the other `App\Providers\Project\*` entries)
- Test: `tests/Unit/MessageTypeRegistrationTest.php`

**Step 1: Write the failing test**

```php
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
```

**Step 2: Run to verify it fails**

Run: `/usr/local/opt/php@8.3/bin/php vendor/bin/phpunit --filter MessageTypeRegistrationTest`
Expected: FAIL — registry resolves empty (0 types) so the count assertion fails.

**Step 3: Implement the provider**

`app/Providers/Project/MessageTypeServiceProvider.php`:

```php
<?php

namespace App\Providers\Project;

use App\MessageTypes\CurrencyType;
use App\MessageTypes\FileReferenceType;
use App\MessageTypes\LocationType;
use App\MessageTypes\MetricType;
use App\MessageTypes\MoodType;
use App\MessageTypes\StatusType;
use App\Services\TypeRegistry;
use Illuminate\Support\ServiceProvider;

class MessageTypeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TypeRegistry::class, function () {
            return new TypeRegistry([
                new CurrencyType(),
                new LocationType(),
                new StatusType(),
                new FileReferenceType(),
                new MetricType(),
                new MoodType(),
            ]);
        });
    }
}
```

**Step 4: Register the provider**

In `config/app.php`, add to the `providers` array immediately after `App\Providers\Project\UserServiceProvider::class,`:

```php
        App\Providers\Project\MessageTypeServiceProvider::class,
```

**Step 5: Run to verify it passes**

Run: `/usr/local/opt/php@8.3/bin/php vendor/bin/phpunit --filter MessageTypeRegistrationTest`
Expected: PASS.

**Step 6: Commit**

```bash
git add app/Providers/Project/MessageTypeServiceProvider.php config/app.php tests/Unit/MessageTypeRegistrationTest.php
git commit -m "feat: register TypeRegistry singleton with six types"
```

---

## Task 6: The `InvalidEnvelopeException`

A `HttpResponseException` subclass that renders the registry's error descriptor as a `422`.

**Files:**
- Create: `app/Exceptions/InvalidEnvelopeException.php`

**Step 1: Implement**

```php
<?php

namespace App\Exceptions;

use Illuminate\Http\Exceptions\HttpResponseException;

class InvalidEnvelopeException extends HttpResponseException
{
    /** @param array<string, mixed> $descriptor */
    public function __construct(array $descriptor)
    {
        parent::__construct(response()->json($descriptor, 422));
    }
}
```

**Step 2: Verify it parses**

Run: `/usr/local/opt/php@8.3/bin/php -l app/Exceptions/InvalidEnvelopeException.php`
Expected: `No syntax errors detected`.

**Step 3: Commit**

```bash
git add app/Exceptions/InvalidEnvelopeException.php
git commit -m "feat: add InvalidEnvelopeException rendering 422"
```

---

## Task 7: Enforce in `MessageService` (TDD)

Validate `content` in `newThread` (before the thread is created) and `body` in `newMessage`, throwing `InvalidEnvelopeException` on failure.

**Files:**
- Modify: `app/Services/MessageService.php`
- Test: `tests/Feature/MessageEnvelopeTest.php`

**Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Exceptions\InvalidEnvelopeException;
use App\Interfaces\MessageServiceInterface;
use App\Models\Thread;
use App\Models\User;
use Tests\TestCase;

class MessageEnvelopeTest extends TestCase
{
    private MessageServiceInterface $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(MessageServiceInterface::class);
    }

    private function validBody(): array
    {
        return ['type' => 'mood', 'version' => '1.0', 'payload' => ['mood' => 'happy']];
    }

    public function testNewThreadAcceptsValidEnvelope(): void
    {
        $user = User::factory()->create();
        $thread = $this->service->newThread('Subject', $user, $this->validBody());
        $this->assertCount(1, $thread->messages);
    }

    public function testNewThreadRejectsFreeText(): void
    {
        $user = User::factory()->create();
        $this->expectException(InvalidEnvelopeException::class);
        $this->service->newThread('Subject', $user, ['some' => 'data']);
    }

    public function testNewThreadDoesNotCreateOrphanThreadOnInvalidBody(): void
    {
        $user = User::factory()->create();
        $before = Thread::count();
        try {
            $this->service->newThread('Subject', $user, ['some' => 'data']);
        } catch (InvalidEnvelopeException $e) {
            // expected
        }
        $this->assertSame($before, Thread::count());
    }

    public function testNewMessageRejectsInvalidPayload(): void
    {
        $user = User::factory()->create();
        $thread = $this->service->newThread('Subject', $user, $this->validBody());
        $this->expectException(InvalidEnvelopeException::class);
        $this->service->newMessage($thread, $user, [
            'type' => 'mood', 'version' => '1.0', 'payload' => ['mood' => 'ecstatic'],
        ]);
    }
}
```

**Step 2: Run to verify it fails**

Run: `/usr/local/opt/php@8.3/bin/php vendor/bin/phpunit --filter MessageEnvelopeTest`
Expected: FAIL — no validation yet, so the rejection tests don't throw.

**Step 3: Implement enforcement**

In `app/Services/MessageService.php`, add the import:

```php
use App\Exceptions\InvalidEnvelopeException;
use App\Services\TypeRegistry;
```

Add a private guard method:

```php
    /**
     * Validate a typed envelope, throwing on failure.
     *
     * @param  array  $envelope
     */
    private function assertValidEnvelope(array $envelope): void
    {
        $error = app(TypeRegistry::class)->validate($envelope);

        if ($error !== null) {
            throw new InvalidEnvelopeException($error);
        }
    }
```

In `newThread`, validate **before** `Thread::create`:

```php
    public function newThread(string $subject, User $user, array $content, ?array $recipients = []): Thread
    {
        $this->assertValidEnvelope($content);

        /** @var $thread Thread */
        $thread = Thread::create([
            'subject' => $subject,
        ]);
        // ... unchanged
```

In `newMessage`, validate **before** `Message::create`:

```php
    public function newMessage(Thread $thread, User $user, array $content): Message
    {
        $this->assertValidEnvelope($content);

        $message = Message::create([
            'thread_id' => $thread->id,
            'user_id' => $user->id,
            'body' => $content,
        ]);
        // ... unchanged
```

(`newThread` calls `newMessage`, so the content is validated again there — harmless and keeps both entry points self-guarding.)

**Step 4: Run to verify it passes**

Run: `/usr/local/opt/php@8.3/bin/php vendor/bin/phpunit --filter MessageEnvelopeTest`
Expected: PASS.

**Step 5: Commit**

```bash
git add app/Services/MessageService.php tests/Feature/MessageEnvelopeTest.php
git commit -m "feat: enforce typed envelopes in MessageService"
```

---

## Task 8: `GET /types` discovery endpoint (TDD)

**Files:**
- Create: `app/Http/Controllers/TypeController.php`
- Create: `app/Http/Resources/MessageTypeResource.php`
- Modify: `routes/api.php` (outside the `auth:api` group)
- Test: `tests/Feature/TypeDiscoveryTest.php`

**Step 1: Write the failing test**

```php
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
```

**Step 2: Run to verify it fails**

Run: `/usr/local/opt/php@8.3/bin/php vendor/bin/phpunit --filter TypeDiscoveryTest`
Expected: FAIL — route `types` not defined.

**Step 3: Implement the resource**

`app/Http/Resources/MessageTypeResource.php`:

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class MessageTypeResource extends JsonResource
{
    /**
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        /** @var \App\Interfaces\MessageTypeInterface $type */
        $type = $this->resource;

        return [
            'type' => $type->name(),
            'version' => $type->version(),
            'purpose' => $type->purpose(),
            'schema' => $type->schema(),
            'renderer_hint' => $type->rendererHint(),
        ];
    }
}
```

**Step 4: Implement the controller**

`app/Http/Controllers/TypeController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Http\Resources\MessageTypeResource;
use App\Services\TypeRegistry;

class TypeController extends Controller
{
    /**
     * Public discovery of the message type registry.
     *
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function index(TypeRegistry $registry)
    {
        return MessageTypeResource::collection(array_values($registry->all()));
    }
}
```

Note: `MessageTypeResource::collection` wraps in a `data` key by default. The test asserts a top-level array, so disable wrapping for this response. Add to `index` before returning:

```php
        MessageTypeResource::withoutWrapping();
```

(Place it as the first line of `index`.) Alternatively, if the project already calls `JsonResource::withoutWrapping()` globally, this is a no-op — check `app/Providers/AppServiceProvider.php`. If global wrapping is off, the test's top-level array passes without the per-call line; if not, the per-call line is required. Verify by running the test.

**Step 5: Add the route**

In `routes/api.php`, add **outside** the `auth:api` group (e.g. at the top of the file after the `use` statement):

```php
Route::get('types', ['as' => 'types', 'uses' => 'TypeController@index']);
```

Match the existing string-controller style used throughout this file.

**Step 6: Run to verify it passes**

Run: `/usr/local/opt/php@8.3/bin/php vendor/bin/phpunit --filter TypeDiscoveryTest`
Expected: PASS. If the body is wrapped in `data`, add `MessageTypeResource::withoutWrapping();` as described and re-run.

**Step 7: Commit**

```bash
git add app/Http/Controllers/TypeController.php app/Http/Resources/MessageTypeResource.php routes/api.php tests/Feature/TypeDiscoveryTest.php
git commit -m "feat: add public GET /types discovery endpoint"
```

---

## Task 9: HTTP endpoints reject bad envelopes (TDD)

Prove the endpoints return a structured `422` (enforcement reaches the controllers via the service).

**Files:**
- Test: `tests/Feature/MessageEndpointEnvelopeTest.php`

**Step 1: Write the test**

```php
<?php

namespace Tests\Feature;

use App\Models\Thread;
use App\Models\User;
use Tests\TestCase;

class MessageEndpointEnvelopeTest extends TestCase
{
    private function validBody(): array
    {
        return ['type' => 'mood', 'version' => '1.0', 'payload' => ['mood' => 'happy']];
    }

    public function testStoreRejectsFreeTextWith422(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'api')->postJson(route('message.store'), [
            'subject' => 'Hi',
            'content' => ['just' => 'text'],
        ]);

        $response->assertStatus(422);
        $response->assertJson(['error' => 'unknown_type']);
    }

    public function testStoreAcceptsValidEnvelope(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'api')->postJson(route('message.store'), [
            'subject' => 'Hi',
            'content' => $this->validBody(),
        ]);

        $response->assertCreated();
    }

    public function testAppendRejectsInvalidPayloadWith422(): void
    {
        $user = User::factory()->create();
        $thread = app(\App\Interfaces\MessageServiceInterface::class)
            ->newThread('Hi', $user, $this->validBody());

        $response = $this->actingAs($user, 'api')->postJson(route('message.new', ['id' => $thread->id]), [
            'body' => ['type' => 'mood', 'version' => '1.0', 'payload' => ['mood' => 'ecstatic']],
        ]);

        $response->assertStatus(422);
        $response->assertJson(['error' => 'invalid_payload']);
    }
}
```

**Step 2: Run**

Run: `/usr/local/opt/php@8.3/bin/php vendor/bin/phpunit --filter MessageEndpointEnvelopeTest`
Expected: PASS (no code changes needed — enforcement already lives in the service). If it fails, the service enforcement from Task 7 is not wired correctly; fix there.

**Step 3: Commit**

```bash
git add tests/Feature/MessageEndpointEnvelopeTest.php
git commit -m "test: endpoints reject invalid envelopes with structured 422"
```

---

## Task 10: Update factory, seeders, and existing tests to valid envelopes

The existing suite writes free-form bodies and will now fail. Make every write a valid envelope.

**Files:**
- Modify: `database/factories/MessageFactory.php`
- Modify: `tests/Feature/MessageTest.php`
- Modify: `tests/Unit/MessageTest.php`
- Check: `database/seeders/MessagingSeeder.php` (no change expected if it uses the factory)

**Step 1: Rewrite the factory**

Replace the body in `database/factories/MessageFactory.php` `definition()` with a randomly chosen valid envelope:

```php
    public function definition()
    {
        $bodies = [
            ['type' => 'mood', 'version' => '1.0', 'payload' => ['mood' => 'happy', 'intensity' => 3]],
            ['type' => 'currency', 'version' => '1.0', 'payload' => ['amount' => 142.50, 'currency_code' => 'USD']],
            ['type' => 'location', 'version' => '1.0', 'payload' => ['lat' => 51.5074, 'lng' => -0.1278]],
            ['type' => 'status', 'version' => '1.0', 'payload' => ['state' => 'dispatched', 'reason' => 'carrier_collected']],
            ['type' => 'metric', 'version' => '1.0', 'payload' => ['name' => 'ambient_temperature', 'value' => 22.4, 'unit' => 'celsius']],
        ];

        return [
            'thread_id' => Thread::inRandomOrder()->first()->id,
            'user_id'   => User::inRandomOrder()->first()->id,
            'body'      => collect($bodies)->random(),
        ];
    }
```

**Step 2: Add a test helper and fix `tests/Unit/MessageTest.php`**

At the top of the `MessageTest` class (Unit), add a helper:

```php
    private function envelope(string $mood = 'happy'): array
    {
        return ['type' => 'mood', 'version' => '1.0', 'payload' => ['mood' => $mood]];
    }
```

Then replace every free-form body passed to `newThread`/`newMessage` with `$this->envelope()`:
- `['some' => 'data']` → `$this->envelope()`
- `['some' => 'content']` → `$this->envelope()`
- `['some', "content #{$i}"]` → `$this->envelope('meh')`

Search the file for `newThread(` and `newMessage(` and update each body argument. The body round-trip assertion in `testServiceMethodNewMessage` still holds because it compares whatever was stored.

**Step 3: Fix `tests/Feature/MessageTest.php`**

In `testMessageControllerNewMethod`, replace:

```php
        $content = ['test' => 'data'];
```

with:

```php
        $content = ['type' => 'mood', 'version' => '1.0', 'payload' => ['mood' => 'happy']];
```

The `store` test already uses `Message::factory()->make()->body`, which is valid after Step 1.

**Step 4: Run the message suites**

Run: `/usr/local/opt/php@8.3/bin/php vendor/bin/phpunit --filter MessageTest`
Expected: PASS (both Unit and Feature `MessageTest`).

**Step 5: Commit**

```bash
git add database/factories/MessageFactory.php tests/Feature/MessageTest.php tests/Unit/MessageTest.php
git commit -m "test: adopt valid typed envelopes across factory and message tests"
```

---

## Task 11: Full suite green + final review

**Step 1: Run the entire suite**

Run: `/usr/local/opt/php@8.3/bin/php vendor/bin/phpunit`
Expected: PASS, no failures, no errors. If `FrontEndTest` needs a built Vite manifest, run `npm ci && npm run build` first (as CI does).

**Step 2: Confirm seeding still works (optional sanity)**

Run: `/usr/local/opt/php@8.3/bin/php artisan migrate:fresh --seed --env=testing` against a scratch DB, or trust the factory unit coverage.

**Step 3: Final commit if anything changed**

```bash
git add -A
git commit -m "chore: typed-payload protocol — suite green"
```

---

## Done criteria

- Six types registered; `GET /types` returns all six unauthenticated with `{type, version, purpose, schema, renderer_hint}`.
- `MessageService` rejects any non-conforming body with a structured `422` (`invalid_envelope` / `unknown_type` / `unknown_version` / `invalid_payload`).
- No free-text strings accepted by any schema; `additionalProperties:false` everywhere.
- Full PHPUnit suite green under PHP 8.3.
- All work committed on `feat/typed-payload-protocol`. Opening the PR is the final, separate step (per the PR-only policy).