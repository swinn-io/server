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
| `MessageTypeServiceProvider` | `app/Providers/MessageTypeServiceProvider.php` | Registers the six types into the registry singleton; registered in `config/app.php`. |
| `ValidEnvelope` rule | `app/Rules/ValidEnvelope.php` | Reusable validation rule used on `content` (store) and `body` (append). |
| `TypeController` | `app/Http/Controllers/TypeController.php` | `GET /types`. |
| `MessageTypeResource` | `app/Http/Resources/MessageTypeResource.php` | Serializes a type to `{type, version, purpose, schema, renderer_hint}`. |

## 4. Validation & Error Flow

`ValidEnvelope`, applied to the body field on every write:

1. Body is an object with **exactly** keys `type`, `version`, `payload`. Extra top-level keys → reject.
2. `type` is a string and known in the registry, else `422 {"error":"unknown_type","type":"…"}`.
3. `version` matches the registered type's version, else `422 {"error":"unknown_version","type":"…","version":"…"}`.
4. `payload` validates against the type's JSON Schema (via `opis/json-schema`), else `422 {"error":"invalid_payload","violations":[…]}`.
5. On pass, the message is stored unchanged (`body` cast to array as today).

`MessageStoreRequest` / `MessageNewRequest` override `failedValidation()` to emit these structured bodies.

## 5. Starter Schemas (all `additionalProperties: false`)

### `currency` — renderer hint `CurrencyCard`
- `amount` — number, required
- `currency_code` — string, required, pattern `^[A-Z]{3}$` (ISO 4217)

### `location` — renderer hint `LocationPin`
- `lat` — number, required, minimum −90, maximum 90
- `lng` — number, required, minimum −180, maximum 180

### `status` — renderer hint `StatusBadge`
- `state` — string, required, pattern `^[a-z][a-z0-9_]*$`
- `reason` — string, optional, pattern `^[a-z][a-z0-9_]*$`, maxLength 60

### `file_reference` — renderer hint `FileCard`
- `url` — string, required, format `uri`
- `mime_type` — string, required, pattern `^[a-z]+/[a-z0-9.+-]+$`
- `size_bytes` — integer, required, minimum 1
- *(`name` dropped — a filename is free text, violating the no-editable-string rule)*

### `metric` — renderer hint `MetricDisplay`
- `name` — string, required, pattern `^[a-z][a-z0-9_]*$`
- `value` — number, required
- `unit` — string, required, pattern `^[a-z][a-z0-9_]*$`
- `recorded_at` — string, optional, format `date-time` (ISO 8601)

### `mood` — renderer hint `MoodCard`
Share your mood with your lads. The mood is a closed enum — no free text.
- `mood` — string, required, enum: `happy`, `sad`, `angry`, `excited`, `tired`, `anxious`, `calm`, `bored`, `grateful`, `stressed`, `loved`, `meh`
- `intensity` — integer, optional, minimum 1, maximum 5

## 6. Discovery Endpoint

`GET /types` — **unauthenticated**, registered in `routes/api.php` outside the `auth:api` group. Returns an array of `{type, version, purpose, schema, renderer_hint}` for all registered types.

## 7. Adopting Types Across the Existing Surface

"Ensure the endpoints use them" has a ripple: the current `MessageFactory`, seeders, and existing message feature tests post arbitrary arrays, which will now `422`. This run updates them to emit valid typed envelopes so the suite and `db:seed` stay green.

- `database/factories/MessageFactory.php` — emit a valid envelope (e.g. a random starter type).
- Seeders that create messages — use valid envelopes.
- Existing message feature tests — assert against valid envelopes; add the rejection cases below.

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