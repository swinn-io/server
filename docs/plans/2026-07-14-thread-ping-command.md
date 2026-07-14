# `thread:ping` Artisan Command Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Add a dual-mode `thread:ping` artisan command that pings certain users inside an existing thread, or creates a new thread whose opening message is the ping.

**Architecture:** A "ping" is a new `ping` message *type* (JSON-Schema-validated `{type,version,payload}` envelope, payload lists the pinged user IDs). The command builds that envelope and calls the existing `MessageService::newMessage()` (existing-thread mode) or `newThread()` (create mode), so persistence + Reverb broadcast to all participants reuse the current pipeline with zero new notification code.

**Tech Stack:** Laravel 13 (legacy skeleton — `app/Console/Kernel.php` auto-loads `app/Console/Commands/`), PHPUnit 12, Opis JSON Schema, `cmgmyr/messenger` Thread/Participant/Message models (all IDs are UUID strings).

**Design doc:** `docs/plans/2026-07-14-thread-ping-command-design.md`

**Branch:** `feat/thread-ping-command` (already created; design doc already committed on it).

**PHP binary:** use `/usr/local/opt/php@8.3/bin/php` for all `artisan`/`phpunit`/`pint`/`phpstan` invocations.

---

## Reference: house patterns to mirror

- **Message type:** `app/MessageTypes/MoodType.php` — implements `App\Interfaces\MessageTypeInterface` (`name`, `version`, `purpose`, `schema`, `rendererHint`). `schema()` returns an Opis JSON-Schema array.
- **Type registration:** `app/Providers/Project/MessageTypeServiceProvider.php` — constructs `new TypeRegistry([...])` with one instance per type.
- **Type unit tests:** `tests/Unit/MessageTypeTest.php` (the `accepts()` helper + per-type schema assertions) and `tests/Unit/MessageTypeRegistrationTest.php` (asserts the registry count + `has()` per name).
- **Service tests / thread setup:** `tests/Unit/MessageTest.php` — `setUp()` resolves `app(MessageServiceInterface::class)`, uses `User::factory()->create()`, `newThread($subject,$user,$envelope,$recipientIds)`, and `Notification::fake()` + `Notification::assertSentTo()`.
- **Base `TestCase`** already uses `DatabaseMigrations`, so every test gets a fresh migrated DB — no extra trait needed.
- **`Message->body`** is cast to `array` (so `$message->body['type']` works).
- Service method signatures (from `app/Interfaces/MessageServiceInterface.php`):
  - `newMessage(Thread $thread, User $user, array $content): Message`
  - `newThread(string $subject, User $user, array $content, array $recipients = []): Thread`
  - Both call `assertValidEnvelope()`, which validates the envelope against `TypeRegistry` — so `PingType` must be registered (Task 1) before the command (Tasks 2+) can post a ping.

---

## Task 1: `PingType` message type + registration

**Files:**
- Create: `app/MessageTypes/PingType.php`
- Modify: `app/Providers/Project/MessageTypeServiceProvider.php`
- Test: `tests/Unit/MessageTypeTest.php`, `tests/Unit/MessageTypeRegistrationTest.php`

**Step 1: Write the failing tests**

In `tests/Unit/MessageTypeTest.php`, add `use App\MessageTypes\PingType;` to the imports and this method to the class:

```php
public function test_ping(): void
{
    $type = new PingType;
    $this->assertSame('ping', $type->name());
    $this->assertSame('1.0', $type->version());
    $this->assertSame('PingCard', $type->rendererHint());
    $this->assertNotEmpty($type->purpose());

    $this->assertTrue($this->accepts($type->schema(), ['user_ids' => ['u1']]));
    $this->assertTrue($this->accepts($type->schema(), ['user_ids' => ['u1', 'u2'], 'note' => 'please respond']));
    $this->assertFalse($this->accepts($type->schema(), ['user_ids' => []]));               // minItems
    $this->assertFalse($this->accepts($type->schema(), ['user_ids' => ['u1', 'u1']]));      // uniqueItems
    $this->assertFalse($this->accepts($type->schema(), ['note' => 'x']));                   // missing user_ids
    $this->assertFalse($this->accepts($type->schema(), ['user_ids' => ['u1'], 'x' => 1]));  // additionalProperties
}
```

In `tests/Unit/MessageTypeRegistrationTest.php`, update the existing test to expect **7** types including `ping` (rename the method for accuracy):

```php
public function test_registry_resolves_with_all_seven_types(): void
{
    $registry = app(TypeRegistry::class);
    $this->assertCount(7, $registry->all());
    foreach (['currency', 'location', 'status', 'file_reference', 'metric', 'mood', 'ping'] as $name) {
        $this->assertTrue($registry->has($name), "missing type {$name}");
    }
}
```

**Step 2: Run tests to verify they fail**

Run: `/usr/local/opt/php@8.3/bin/php artisan test --compact --filter="test_ping|test_registry_resolves_with_all_seven_types"`
Expected: FAIL — `PingType` class not found / registry count is 6, `ping` missing.

**Step 3: Create `PingType`**

Create `app/MessageTypes/PingType.php`:

```php
<?php

namespace App\MessageTypes;

use App\Interfaces\MessageTypeInterface;

class PingType implements MessageTypeInterface
{
    public function name(): string
    {
        return 'ping';
    }

    public function version(): string
    {
        return '1.0';
    }

    public function purpose(): string
    {
        return 'Nudge specific participants of a thread. The payload lists the pinged user IDs (a targeted mention) so clients may highlight the message for those users. An optional short note may accompany the ping.';
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['user_ids'],
            'properties' => [
                'user_ids' => [
                    'type' => 'array',
                    'minItems' => 1,
                    'uniqueItems' => true,
                    'items' => ['type' => 'string'],
                ],
                'note' => ['type' => 'string', 'maxLength' => 280],
            ],
        ];
    }

    public function rendererHint(): string
    {
        return 'PingCard';
    }
}
```

**Step 4: Register it**

In `app/Providers/Project/MessageTypeServiceProvider.php`, add `use App\MessageTypes\PingType;` to the imports and `new PingType,` to the `TypeRegistry` array (after `new MoodType,`).

**Step 5: Run tests to verify they pass**

Run: `/usr/local/opt/php@8.3/bin/php artisan test --compact --filter="test_ping|test_registry_resolves_with_all_seven_types"`
Expected: PASS.

**Step 6: Commit**

```bash
git add app/MessageTypes/PingType.php app/Providers/Project/MessageTypeServiceProvider.php tests/Unit/MessageTypeTest.php tests/Unit/MessageTypeRegistrationTest.php
git commit -m "feat: add ping message type"
```

---

## Task 2: `thread:ping` command — ping an existing thread

**Files:**
- Create: `app/Console/Commands/PingThreadUsers.php`
- Test: `tests/Feature/PingThreadUsersCommandTest.php`

**Step 1: Write the failing test**

Create `tests/Feature/PingThreadUsersCommandTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Interfaces\MessageServiceInterface;
use App\Models\Message;
use App\Models\Thread;
use App\Models\User;
use App\Notifications\MessageCreated;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PingThreadUsersCommandTest extends TestCase
{
    private MessageServiceInterface $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(MessageServiceInterface::class);
    }

    /**
     * A valid typed envelope for seeding threads/messages in tests.
     *
     * @return array<string, mixed>
     */
    private function envelope(string $mood = 'happy'): array
    {
        return ['type' => 'mood', 'version' => '1.0', 'payload' => ['mood' => $mood]];
    }

    private function pingMessage(Thread $thread): ?Message
    {
        return $thread->messages()->get()
            ->first(fn (Message $m) => is_array($m->body) && ($m->body['type'] ?? null) === 'ping');
    }

    public function test_pings_selected_users_in_an_existing_thread(): void
    {
        Notification::fake();

        $sender = User::factory()->create();
        $recipient = User::factory()->create();
        $other = User::factory()->create();

        $thread = $this->service->newThread('Subject', $sender, $this->envelope(), [$recipient->id, $other->id]);

        $this->artisan('thread:ping', [
            '--thread' => $thread->id,
            '--from' => $sender->id,
            '--user' => [$recipient->id],
        ])->assertSuccessful();

        $ping = $this->pingMessage($thread);
        $this->assertNotNull($ping);
        $this->assertSame([$recipient->id], $ping->body['payload']['user_ids']);

        // It is a normal thread message: every participant is notified.
        Notification::assertSentTo($other, MessageCreated::class);
    }
}
```

**Step 2: Run to verify it fails**

Run: `/usr/local/opt/php@8.3/bin/php artisan test --compact tests/Feature/PingThreadUsersCommandTest.php`
Expected: FAIL — command `thread:ping` not defined.

**Step 3: Create the command (existing-thread mode + shared setup)**

Create `app/Console/Commands/PingThreadUsers.php`:

```php
<?php

namespace App\Console\Commands;

use App\Interfaces\MessageServiceInterface;
use App\Models\Thread;
use App\Models\User;
use Illuminate\Console\Command;

class PingThreadUsers extends Command
{
    /**
     * @var string
     */
    protected $signature = 'thread:ping
        {--thread= : Existing thread UUID to ping into (omit to create a new thread)}
        {--from= : Sender / thread-creator user UUID}
        {--user=* : User UUID(s) to ping}
        {--subject= : Subject for a NEW thread (required when --thread is omitted)}
        {--note= : Optional short note included in the ping payload}';

    /**
     * @var string
     */
    protected $description = 'Ping certain users in an existing thread, or create a new thread whose opening message is the ping.';

    public function handle(MessageServiceInterface $service): int
    {
        $threadId = $this->option('thread');
        $subject = $this->option('subject');
        $fromId = $this->option('from');
        /** @var array<int, string> $userIds */
        $userIds = array_values(array_unique($this->option('user')));
        $note = $this->option('note');

        if (! is_string($fromId) || $fromId === '') {
            $this->error('The --from option (sender user UUID) is required.');

            return self::FAILURE;
        }

        if ($userIds === []) {
            $this->error('Provide at least one --user to ping.');

            return self::FAILURE;
        }

        $sender = User::find($fromId);
        if ($sender === null) {
            $this->error("Sender user {$fromId} not found.");

            return self::FAILURE;
        }

        /** @var \Illuminate\Database\Eloquent\Collection<int, User> $users */
        $users = User::findMany($userIds);
        if ($users->count() !== count($userIds)) {
            $found = $users->pluck('id')->all();
            $missing = array_diff($userIds, $found);
            $this->error('These users were not found: '.implode(', ', $missing));

            return self::FAILURE;
        }

        $envelope = [
            'type' => 'ping',
            'version' => '1.0',
            'payload' => array_filter(
                ['user_ids' => $userIds, 'note' => $note],
                fn ($value) => $value !== null,
            ),
        ];

        $thread = Thread::find($threadId);
        if ($thread === null) {
            $this->error("Thread {$threadId} not found.");

            return self::FAILURE;
        }

        $participantIds = $thread->users()->get()->pluck('id')->all();

        if (! in_array($sender->id, $participantIds, true)) {
            $this->error("Sender {$sender->id} is not a participant of thread {$thread->id}.");

            return self::FAILURE;
        }

        $notParticipants = array_diff($users->pluck('id')->all(), $participantIds);
        if ($notParticipants !== []) {
            $this->error('These users are not in the thread: '.implode(', ', $notParticipants));

            return self::FAILURE;
        }

        $message = $service->newMessage($thread, $sender, $envelope);
        $this->info("Pinged {$users->count()} user(s) in thread {$thread->id} (message {$message->id}).");

        return self::SUCCESS;
    }
}
```

> Note: mode selection (existing vs. create) and the `--subject` handling are added in Task 3. For now `--thread` is assumed present; Task 3 introduces the branch and the mode guards, and Task 4 adds the remaining validation error paths.

**Step 4: Run to verify it passes**

Run: `/usr/local/opt/php@8.3/bin/php artisan test --compact tests/Feature/PingThreadUsersCommandTest.php`
Expected: PASS.

**Step 5: Commit**

```bash
git add app/Console/Commands/PingThreadUsers.php tests/Feature/PingThreadUsersCommandTest.php
git commit -m "feat: thread:ping command pings users in an existing thread"
```

---

## Task 3: Create-thread mode

**Files:**
- Modify: `app/Console/Commands/PingThreadUsers.php`
- Test: `tests/Feature/PingThreadUsersCommandTest.php`

**Step 1: Write the failing test**

Add to `tests/Feature/PingThreadUsersCommandTest.php`:

```php
public function test_creates_a_new_thread_and_pings_recipients(): void
{
    Notification::fake();

    $sender = User::factory()->create();
    $a = User::factory()->create();
    $b = User::factory()->create();

    $this->artisan('thread:ping', [
        '--from' => $sender->id,
        '--subject' => 'Heads up',
        '--user' => [$a->id, $b->id],
        '--note' => 'please respond',
    ])->assertSuccessful();

    $thread = Thread::where('subject', 'Heads up')->firstOrFail();

    $participantIds = $thread->users()->get()->pluck('id')->all();
    $this->assertContains($a->id, $participantIds);
    $this->assertContains($b->id, $participantIds);
    $this->assertContains($sender->id, $participantIds);

    $ping = $this->pingMessage($thread);
    $this->assertNotNull($ping);
    $this->assertSame([$a->id, $b->id], $ping->body['payload']['user_ids']);
    $this->assertSame('please respond', $ping->body['payload']['note']);
}
```

**Step 2: Run to verify it fails**

Run: `/usr/local/opt/php@8.3/bin/php artisan test --compact --filter=test_creates_a_new_thread_and_pings_recipients`
Expected: FAIL — with no `--thread`, `Thread::find(null)` returns null → "Thread  not found." failure.

**Step 3: Add the create-mode branch**

In `app/Console/Commands/PingThreadUsers.php`, replace the existing-thread block (from `$thread = Thread::find($threadId);` through the final `return self::SUCCESS;`) with a mode branch:

```php
        if ($threadId === null) {
            $thread = $service->newThread((string) $subject, $sender, $envelope, $userIds);
            $this->info("Created thread {$thread->id} and pinged {$users->count()} user(s).");

            return self::SUCCESS;
        }

        $thread = Thread::find($threadId);
        if ($thread === null) {
            $this->error("Thread {$threadId} not found.");

            return self::FAILURE;
        }

        $participantIds = $thread->users()->get()->pluck('id')->all();

        if (! in_array($sender->id, $participantIds, true)) {
            $this->error("Sender {$sender->id} is not a participant of thread {$thread->id}.");

            return self::FAILURE;
        }

        $notParticipants = array_diff($users->pluck('id')->all(), $participantIds);
        if ($notParticipants !== []) {
            $this->error('These users are not in the thread: '.implode(', ', $notParticipants));

            return self::FAILURE;
        }

        $message = $service->newMessage($thread, $sender, $envelope);
        $this->info("Pinged {$users->count()} user(s) in thread {$thread->id} (message {$message->id}).");

        return self::SUCCESS;
```

**Step 4: Run to verify both mode tests pass**

Run: `/usr/local/opt/php@8.3/bin/php artisan test --compact tests/Feature/PingThreadUsersCommandTest.php`
Expected: PASS (both existing-thread and create-thread tests).

**Step 5: Commit**

```bash
git add app/Console/Commands/PingThreadUsers.php tests/Feature/PingThreadUsersCommandTest.php
git commit -m "feat: thread:ping can create a new thread whose opening message is the ping"
```

---

## Task 4: Mode guards & validation error paths

**Files:**
- Modify: `app/Console/Commands/PingThreadUsers.php`
- Test: `tests/Feature/PingThreadUsersCommandTest.php`

**Step 1: Write the failing tests**

Add to `tests/Feature/PingThreadUsersCommandTest.php`:

```php
public function test_fails_when_neither_thread_nor_subject_is_given(): void
{
    $sender = User::factory()->create();
    $user = User::factory()->create();

    $this->artisan('thread:ping', ['--from' => $sender->id, '--user' => [$user->id]])
        ->assertFailed();

    $this->assertNoPingMessagesExist();
}

public function test_fails_when_both_thread_and_subject_are_given(): void
{
    $sender = User::factory()->create();
    $thread = $this->service->newThread('S', $sender, $this->envelope());

    $this->artisan('thread:ping', [
        '--thread' => $thread->id,
        '--subject' => 'X',
        '--from' => $sender->id,
        '--user' => [$sender->id],
    ])->assertFailed();

    $this->assertNoPingMessagesExist();
}

public function test_fails_when_thread_not_found(): void
{
    $sender = User::factory()->create();
    $user = User::factory()->create();

    $this->artisan('thread:ping', [
        '--thread' => '00000000-0000-0000-0000-000000000000',
        '--from' => $sender->id,
        '--user' => [$user->id],
    ])->assertFailed();

    $this->assertNoPingMessagesExist();
}

public function test_fails_when_a_pinged_user_is_not_a_participant(): void
{
    $sender = User::factory()->create();
    $outsider = User::factory()->create();
    $thread = $this->service->newThread('S', $sender, $this->envelope());

    $this->artisan('thread:ping', [
        '--thread' => $thread->id,
        '--from' => $sender->id,
        '--user' => [$outsider->id],
    ])->assertFailed();

    $this->assertNoPingMessagesExist();
}

public function test_fails_when_sender_is_not_a_participant(): void
{
    $creator = User::factory()->create();
    $participant = User::factory()->create();
    $outsider = User::factory()->create();
    $thread = $this->service->newThread('S', $creator, $this->envelope(), [$participant->id]);

    $this->artisan('thread:ping', [
        '--thread' => $thread->id,
        '--from' => $outsider->id,
        '--user' => [$participant->id],
    ])->assertFailed();

    $this->assertNoPingMessagesExist();
}

public function test_fails_without_users(): void
{
    $sender = User::factory()->create();
    $thread = $this->service->newThread('S', $sender, $this->envelope());

    $this->artisan('thread:ping', ['--thread' => $thread->id, '--from' => $sender->id])
        ->assertFailed();

    $this->assertNoPingMessagesExist();
}
```

Add this helper method to the test class:

```php
private function assertNoPingMessagesExist(): void
{
    $this->assertFalse(
        Message::all()->contains(fn (Message $m) => is_array($m->body) && ($m->body['type'] ?? null) === 'ping'),
        'Expected no ping messages to have been created.',
    );
}
```

**Step 2: Run to verify the new tests fail**

Run: `/usr/local/opt/php@8.3/bin/php artisan test --compact tests/Feature/PingThreadUsersCommandTest.php`
Expected: FAIL — the mode guards (neither/both) don't exist yet, so those two cases won't fail as required (and may error differently).

**Step 3: Add the mode guards**

In `app/Console/Commands/PingThreadUsers.php::handle()`, add these guards immediately after the four `$this->option(...)` reads, **before** the `--from` check:

```php
        if ($threadId !== null && $subject !== null) {
            $this->error('Provide either --thread (ping an existing thread) or --subject (create one), not both.');

            return self::FAILURE;
        }

        if ($threadId === null && $subject === null) {
            $this->error('Give --thread to ping an existing thread, or --subject to create one.');

            return self::FAILURE;
        }
```

(The remaining error paths — sender/user not found, thread not found, non-participant sender/user, missing users — are already implemented in Tasks 2–3; these tests simply lock them in.)

**Step 4: Run to verify all tests pass**

Run: `/usr/local/opt/php@8.3/bin/php artisan test --compact tests/Feature/PingThreadUsersCommandTest.php`
Expected: PASS (all mode + failure cases).

**Step 5: Commit**

```bash
git add app/Console/Commands/PingThreadUsers.php tests/Feature/PingThreadUsersCommandTest.php
git commit -m "feat: thread:ping mode guards and validation error paths"
```

---

## Task 5: Final verification

**Step 1: Full suite + static analysis**

```bash
/usr/local/opt/php@8.3/bin/php artisan test --compact
/usr/local/opt/php@8.3/bin/php ./vendor/bin/pint --test
/usr/local/opt/php@8.3/bin/php -d memory_limit=1G ./vendor/bin/phpstan analyse
```

All must pass clean. If `pint --test` reports fixes, run `/usr/local/opt/php@8.3/bin/php ./vendor/bin/pint`, review, and commit. If `phpstan` flags a real type issue, fix the underlying cause (do not add ignores) and re-run.

**Step 2: Manual smoke (optional, if a local DB with users exists)**

```bash
/usr/local/opt/php@8.3/bin/php artisan thread:ping --help
```
Expected: shows the signature with all five options.

---

## Task 6: Open the PR

```bash
git push -u origin feat/thread-ping-command
gh pr create --base master --title "feat: thread:ping artisan command" --body "$(cat <<'EOF'
## Summary
- Adds a dual-mode `thread:ping` artisan command:
  - `--thread=UUID` → post a ping into an existing thread (the sender and every `--user` must already be participants).
  - `--subject="…"` (no `--thread`) → create a new thread whose recipients are the pinged users and whose opening message is the ping.
- A ping is a new `ping` **message type** (JSON-Schema-validated envelope; `payload.user_ids` records who was pinged as a mention target). It reuses `MessageService::newMessage()`/`newThread()`, so it persists and broadcasts `MessageCreated` to all participants over the existing Reverb pipeline — no new notification code.
- Guards: exactly one of `--thread`/`--subject`; `--from` and at least one `--user` required; friendly errors for not-found/non-participant users; nothing is persisted on any failure.

Design: `docs/plans/2026-07-14-thread-ping-command-design.md`

## Test plan
- [x] `tests/Unit/MessageTypeTest.php` (ping schema) + `MessageTypeRegistrationTest` (now 7 types).
- [x] `tests/Feature/PingThreadUsersCommandTest.php` — both modes + all failure paths.
- [x] Full PHPUnit suite, pint, phpstan (level max) green.

🤖 Generated with [Claude Code](https://claude.com/claude-code)
EOF
)"
```
