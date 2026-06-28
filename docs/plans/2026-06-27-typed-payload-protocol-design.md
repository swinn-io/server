# Typed-Payload Protocol — Design (Backend, Steps 1–3)

**Status:** Approved 2026-06-27
**Source brief:** `docs/SWINN_DESIGN.md`
**Scope this run:** Backend only — envelope enforcement, type registry + schema validation, `GET /types` discovery. Web UI renderers (brief Step 4) deferred.
**Delivery:** single feature branch `feat/typed-payload-protocol`, one PR.

---

## 1. Goal

Make the typed-data envelope explicit and contract-driven. Every message body must be a typed JSON envelope validated against a closed, code-defined registry of types at write time. **Free text is structurally impossible**: the envelope accepts exactly `{type, version, payload}`, and every payload sets `additionalProperties: false` with every string constrained by pattern/format/enum.

This realizes the brief's "No free text" and "Data consistency" principles on the existing `auth:api` message endpoints.

## 2. Constraints & Decisions

- **Maximally strict schemas.** `additionalProperties: false` on every payload; every string field constrained; no free-form string fields (the brief's `file_reference.name` is dropped).
- **Contracts live in `app/Interfaces`** with the `*Interface` suffix — the project's existing convention (`UserServiceInterface`, etc.). No new `app/Contracts` directory. So the brief's `App\Contracts\MessageType` becomes `App\Interfaces\MessageTypeInterface`.
- **Interface uses methods, not properties.** PHP interfaces cannot declare typed public properties as the brief sketched; the interface declares methods.
- **Two write paths, two field names.** New-thread (`MessageStoreRequest`) carries the body as `content`; append (`MessageNewRequest`) carries it as `body`. Both funnel into `MessageService::newMessage(...)`. Envelope validation must cover **both** fields.
- **Validator dependency:** `opis/json-schema` (none currently installed).
- **Error contract:** 422 responses are shaped via `failedValidation()` to match the brief's `{"error": "...", ...}` bodies rather than Laravel's default `{message, errors}`.
- **PHP 8.3**, test DB `swinn-test`.

## 3. Components

| Component | Path | Responsibility |
|-----------|------|----------------|
| `MessageTypeInterface` | `app/Interfaces/MessageTypeInterface.php` | Contract: `name()`, `version()`, `purpose()`, `schema(): array`, `rendererHint()`. |
| Type classes | `app/MessageTypes/{Currency,Location,Status,FileReference,Metric,Mood}Type.php` | One per starter type; implement the interface. |
| `TypeRegistry` | `app/Services/TypeRegistry.php` | Singleton. `all()`, `has($name)`, `get($name)`, `validate(array $envelope): array` (returns violations / error descriptor). |
| `MessageTypeServiceProvider` | `app/Providers/Project/MessageTypeServiceProvider.php` | Registers the six types into the registry singleton; added to the `config/app.php` providers array alongside the other `Project` providers. |
| `InvalidEnvelopeException` | `app/Exceptions/InvalidEnvelopeException.php` | Extends `HttpResponseException`; renders the structured `422` from a descriptor. |
| `MessageService` (modify) | `app/Services/MessageService.php` | `newThread`/`newMessage` call `TypeRegistry::validate` and throw `InvalidEnvelopeException` on failure. |
| `TypeController` | `app/Http/Controllers/TypeController.php` | `GET /types`. |
| `MessageTypeResource` | `app/Http/Resources/MessageTypeResource.php` | Serializes a type to `{type, version, purpose, schema, renderer_hint}`. |

## 4. Validation & Error Flow

**Enforcement lives in `MessageService`** so every write path — HTTP endpoints, seeders, internal callers, tests — is covered. The brief's first principle is "data consistency everywhere, always," and the Unit suite writes through the service directly, so request-layer-only validation would leave a hole. The endpoints enforce transitively because they call the service.

`MessageService::newThread()` validates `$content` **before** creating the thread (no orphan thread on failure); `MessageService::newMessage()` validates `$body` before creating the message. Both delegate to `TypeRegistry::validate(array $envelope): ?array`, which returns `null` on success or an error descriptor. On a non-null descriptor the service throws `InvalidEnvelopeException`, which renders the structured `422`.

`TypeRegistry::validate()` checks, in order:

1. Envelope is an object with **exactly** keys `type`, `version`, `payload` (`type`/`version` strings, `payload` object). Otherwise → `{"error":"invalid_envelope","message":"…"}`.
2. `type` known in the registry, else `{"error":"unknown_type","type":"…"}`.
3. `version` matches the registered type's version, else `{"error":"unknown_version","type":"…","version":"…"}`.
4. `payload` validates against the type's JSON Schema (via `opis/json-schema`), else `{"error":"invalid_payload","violations":[…]}`.

`InvalidEnvelopeException extends Illuminate\Http\Exceptions\HttpResponseException`, built from the descriptor; its response is `response()->json($descriptor, 422)`. The `MessageStoreRequest` / `MessageNewRequest` keep only their basic `required|array` rules — the envelope contract is enforced one layer deeper.

## 5. Starter Schemas (all `additionalProperties: false`)

### `currency` — renderer hint `CurrencyCard`
- `amount` — number, required
- `currency_code` — string, required, pattern `^[A-Z]{3}$` (ISO 4217)

### `location` — renderer hint `LocationPin`
- `lat` — number, required, minimum −90, maximum 90
- `lng` — number, required, minimum −180, maximum 180

### `status` — renderer hint `StatusBadge`
- `state` — string, required, pattern `^[a-z][a-z0-9_]{0,59}$` (bounded slug; the state vocabulary is the sending system's domain per SWINN_DESIGN-3 §5.3, so it stays a length-capped slug rather than an enum)
- `reason` — string, optional, pattern `^[a-z][a-z0-9_]{0,59}$`

### `file_reference` — renderer hint `FileCard`
- `url` — string, required, format `uri`
- `mime_type` — string, required, pattern `^[a-z]+/[a-z0-9.+-]+$`
- `size_bytes` — integer, required, minimum 1
- *(`name` dropped — a filename is free text, violating the no-editable-string rule)*

### `metric` — renderer hint `MetricDisplay`
Adopts the controlled vocabulary — the open `name`/`unit` slug patterns are replaced by two **closed enums** plus a cross-field compatibility matrix (`MetricType::COMPATIBLE_UNITS`).
- `quantity` — string, required, enum (keys of the compatibility matrix: `temperature`, `humidity`, `pressure`, `speed`, `distance`, `mass`, `energy`, `power`, `voltage`, `current`, `frequency`, `luminance`, `co2`, `pm2_5`, `pm10`, `battery_level`, `signal_strength`)
- `value` — number, required
- `unit` — string, required, enum (all units across the matrix); **cross-field**: must be compatible with `quantity` or the write is rejected `invalid_payload`
- `recorded_at` — string, optional, format `date-time` (ISO 8601)

**Cross-field validation.** Types whose constraints span fields implement `App\Interfaces\CrossFieldValidatableInterface` (`validate(array $payload): array`, `constraints(): array`). `TypeRegistry::validate()` runs `validate()` after JSON Schema passes; a non-empty violation list yields `invalid_payload`. The `constraints()` vocabulary (e.g. `compatible_units`) is surfaced on `GET /types` so clients can discover valid pairings.

### `mood` — renderer hint `MoodCard`
Share your mood with your lads. The mood is a closed enum — no free text.
- `mood` — string, required, enum: `happy`, `sad`, `angry`, `excited`, `tired`, `anxious`, `calm`, `bored`, `grateful`, `stressed`, `loved`, `meh`
- `intensity` — integer, optional, minimum 1, maximum 5

## 6. Discovery Endpoint

`GET /types` — **unauthenticated**, registered in `routes/api.php` outside the `auth:api` group. Returns an array of `{type, version, purpose, schema, renderer_hint}` for all registered types.

## 7. Adopting Types Across the Existing Surface

"Ensure the endpoints use them" has a ripple: the current `MessageFactory`, seeders, and existing message feature tests post arbitrary arrays, which will now `422`. This run updates them to emit valid typed envelopes so the suite and `db:seed` stay green.

- `database/factories/MessageFactory.php` — currently emits unversioned `{type, payload}` bodies (`weather`, plus a `currency` payload that does not match the strict schema). Rewrite to emit a valid versioned envelope for a random starter type.
- `database/seeders/MessagingSeeder.php` — uses the factory; verify it produces valid envelopes after the rewrite.
- `tests/Feature/MessageTest.php` — `store` uses the factory body (valid after rewrite); `new` posts `['test' => 'data']` → replace with a valid envelope. Add the rejection cases below.
- `tests/Unit/MessageTest.php` — calls `MessageService::newThread/newMessage` directly with free-form bodies (`['some' => 'data']`, `['some', "content #{$i}"]`). Because enforcement is now in the service, **every** such literal must become a valid envelope. A small test helper (`validEnvelope($type, $payload)`) keeps this DRY.

## 8. Testing

- **Per type:** valid payload passes; each required field missing fails; wrong scalar type fails; extra property fails (`additionalProperties:false`).
- **Envelope:** missing `type` / `version` / `payload`; non-object `payload`; extra top-level key → 422 with the right `error` code.
- **Registry:** unknown `type` → `unknown_type`; mismatched `version` → `unknown_version`.
- **Endpoints:** `POST /message` and `POST /message/{id}` reject bad envelopes and accept good ones (201).
- **Discovery:** `GET /types` returns all six unauthenticated; each entry has all five fields.
- Run under PHP 8.3 against `swinn-test`.

## 9. Out of Scope (this run)

- Web UI renderer map and message components (brief Step 4).
- Runtime type registration, multi-version coexistence beyond a single version per type, `transit`/webhook types (brief §5).