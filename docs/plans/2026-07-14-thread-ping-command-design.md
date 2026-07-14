# `thread:ping` Artisan Command — Design

**Status:** Approved 2026-07-14
**Source:** ops/admin need to nudge specific participants of a thread from the CLI — either inside an existing thread or by spinning up a new thread whose opening message is the ping.

## 1. Goal

Add an artisan command, `thread:ping`, that an operator/admin runs to "ping" a set of users. It works in two modes:

- **Ping an existing thread** — post a ping message into a given thread, targeting certain existing participants.
- **Create a ping thread** — start a new thread with the given users as recipients, whose opening message is the ping.

A "ping" is a new typed message (`ping`) whose payload lists the pinged user IDs (a mention/target list). It is a normal thread message: it is broadcast to **all** participants over the existing Reverb pipeline; the `payload.user_ids` records who was pinged so clients can highlight it.

## 2. Constraints & Decisions

- **A ping is a message type, not a bespoke notification.** The command builds a `ping` envelope and calls the existing `MessageService` methods, which already persist the message and broadcast `MessageCreated` to every participant. No new notification/broadcast plumbing. Rejected a dedicated `Pinged` notification (contradicts the "visible in-thread message that notifies all" decision and would duplicate the pipeline) and rejected free-text system messages (the app has no untyped messages — every message is a JSON-Schema-validated `{type, version, payload}` envelope enforced by `MessageService::assertValidEnvelope()`).
- **"Certain users" = mention targets in the payload.** The ping notifies all participants (per the approved behavior); the selected user IDs live in `payload.user_ids` so those users' clients can highlight/badge the message. It is not a restriction on who gets notified.
- **Dual mode maps onto existing service methods.** `--thread` given → `MessageService::newMessage($thread, $sender, $envelope)`. `--thread` omitted → `MessageService::newThread($subject, $sender, $envelope, $userIds)` (which creates the thread, adds the recipients as participants, posts the opening message, and broadcasts). Create-mode is essentially free.
- **`--subject` is required in create mode** (explicit, no silent default), so a new thread always has a meaningful subject.
- **Membership rules differ per mode.** Existing-thread mode: the sender and every pinged user must **already** be participants (that is what "ping certain users *in the same thread*" means) — validated, error otherwise, nothing added. Create mode: the pinged users are the new thread's recipients and are added as participants by `newThread()`; they only need to be valid users.
- **Non-interactive.** Arg/option-driven, `--no-interaction`-friendly (fits ops use and any future scheduler). No Laravel Prompts UI (YAGNI).
- **First command in the app.** `app/Console/Commands/` does not exist yet; this creates it. Follow Laravel command conventions and existing house style.

## 3. Components

| Component | Path | Change |
|-----------|------|--------|
| Ping message type | `app/MessageTypes/PingType.php` (new) | Implements `MessageTypeInterface` (mirrors `MoodType`): `name()='ping'`, `version()='1.0'`, `purpose()=…`, `rendererHint()='PingCard'`, and the JSON schema below. |
| Type registration | `app/Providers/Project/MessageTypeServiceProvider.php` | Register `PingType` alongside the existing six types so `TypeRegistry` knows it. |
| Command | `app/Console/Commands/PingThreadUsers.php` (new) | Signature `thread:ping` (below); dual-mode dispatch to `newMessage`/`newThread`. |

**`PingType` JSON schema (payload):**
```
type: object
additionalProperties: false
required: [user_ids]
properties:
  user_ids: { type: array, minItems: 1, uniqueItems: true, items: { type: string } }
  note:     { type: string, maxLength: 280 }   # optional
```

**Command signature:**
```
thread:ping
  {--thread=  : Existing thread UUID to ping into (omit to create a new thread)}
  {--from=    : Sender / thread-creator user UUID}
  {--user=*   : User UUID(s) to ping}
  {--subject= : Subject for a NEW thread (required when --thread is omitted)}
  {--note=    : Optional short note included in the ping payload}
```

## 4. Data Flow

```
CLI options
  → resolve sender User (--from)
  → resolve pinged Users (--user*)
  → build envelope { type:'ping', version:'1.0', payload:{ user_ids:[…], note?:… } }
  → MODE:
      --thread given  → resolve Thread; assert sender + all pinged users are participants
                        → MessageService::newMessage(thread, sender, envelope)
      --thread absent → require --subject
                        → MessageService::newThread(subject, sender, envelope, userIds)
  → Message persisted + MessageCreated broadcast to all participants (existing pipeline)
  → pinged users' clients highlight via payload.user_ids
```

## 5. Error Handling

Each of these prints an error line and returns a non-zero exit code, persisting nothing:

- Neither `--thread` nor `--subject` given → "Give --thread to ping an existing thread, or --subject to create one."
- Both `--thread` and `--subject` given → ambiguous-mode error.
- `--from` missing, or the sender user not found.
- No `--user` given (need at least one).
- Any `--user` UUID not found.
- **Existing mode:** thread not found; sender not a participant; any pinged user not a participant (error names which).
- Envelope validity is additionally enforced by `MessageService::assertValidEnvelope()` via the new `PingType` schema (belt-and-suspenders; the command always builds a valid one).

Use `Command::SUCCESS` / `Command::FAILURE` for exit codes.

## 6. Testing

This repo has real PHPUnit backend test infrastructure, so both are genuine tests:

- **`tests/Unit/PingTypeTest.php`** — mirrors the existing message-type tests: a valid ping envelope passes `TypeRegistry::validate()`; envelopes missing `user_ids`, with an empty `user_ids`, or with extra properties fail.
- **`tests/Feature/PingThreadUsersCommandTest.php`** — covers both modes and failures:
  - **Existing mode happy path:** thread with participants; run `thread:ping --thread --from --user…`; assert exit `SUCCESS`, a `Message` row created with `body.type === 'ping'` and `body.payload.user_ids` equal to the selected IDs, and (`Notification::fake`) `MessageCreated` sent to all participants.
  - **Create mode happy path:** run without `--thread` but with `--subject --from --user…`; assert a new `Thread` created, the pinged users are participants, the opening `Message` is the ping envelope, exit `SUCCESS`.
  - **Failure paths:** thread not found; a `--user` not a participant (existing mode); sender not a participant; neither/both of `--thread`/`--subject`; no `--user` — each returns non-zero and persists nothing.
- `pint`, `phpstan` (level max) stay green.
