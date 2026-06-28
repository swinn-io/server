# Swinn — Open Data-Communication Service
## Design Brief · v0.3 · 2026

> **What changed in v0.3:** Full security, data cleanness, and privacy audit applied.
> Added: thread-level authorization model, rate limiting, URI validation policy, token lifecycle,
> quantity/unit compatibility matrix, coordinate precision and UTC normalisation requirements,
> data retention and erasure model, privacy posture, and re-identification risk acknowledgement.

---

## 1. Vision & Principles

### 1.1 What Swinn Is

WhatsApp for structured data. A thread is a channel. A message is a typed data envelope. Any authorized client — a web UI, a mobile app, a machine process, a webhook receiver — participates on equal footing. There is no privileged client.

The API is the product. The web UI is the reference human client, nothing more.

### 1.2 Principles

| Principle | Description |
|-----------|-------------|
| Data consistency | Every message of a given type has the same shape, everywhere, always. The registry enforces this at write time. |
| Transparency | Schemas and purposes are public, machine-readable, and discoverable via API. No hidden contracts — including to data subjects, not just developers. |
| No free text, anywhere | Plain text is not a message type. No field in any payload may carry a value that a human authored as descriptive text. Every string must be a coordinate, a timestamp, a URI, or a slug from a declared controlled vocabulary. |
| Signals, not interpretations | Swinn carries facts about reality, not opinions about it. What data *means* is determined by the consuming client, not encoded in the payload. |
| Participant-scoped access | A valid token is necessary but not sufficient. Read and write access to a thread requires explicit Participant membership. No token holder can access a thread they are not a participant of. |
| Symmetric clients | Machine clients and human clients are indistinguishable at the protocol level. Both use the same OAuth2-secured REST API and the same access control model. |
| YAGNI | The registry is closed and curated. New types are added with documented purpose, not speculatively. |
| MIT License | The codebase is fully open. Anyone may fork, extend, or self-host with a different type vocabulary. |

### 1.3 The Free-Text Rule

Every string field in every payload must be one of:

- A **timestamp** (ISO 8601, UTC required — see §2.4)
- A **URI** (HTTPS only, allowlist-validated — see §2.5)
- A **controlled vocabulary slug** (`snake_case`, declared as a closed enum in the type's schema)
- A **standardised code** (ISO 4217 currency, IANA MIME type, WGS84 coordinate)

No field may carry a value a human typed as descriptive text. `label`, `name`, `description`, `reason` as free strings are not permitted in any type — in this version or future versions — unless the value is drawn from a declared, finite enum.

### 1.4 Privacy Posture

Swinn carries data categories that attract legal obligations in most jurisdictions:

- `location` — precise geographic coordinates constitute personal data under GDPR and equivalent frameworks
- `currency` — financial transaction data
- `file_reference` — pointers to potentially sensitive documents
- `metric` — may include biometric or health-adjacent measurements (battery level of a medical device, etc.)

**Swinn's position:** Swinn is a data transport. It does not determine the legal basis for processing the data it carries — that is the responsibility of the deploying operator. However, the core design must not make compliance structurally impossible. The following are therefore first-class design requirements, not optional operator concerns:

- A defined data retention model (§7)
- A defined erasure mechanism (§7)
- No uncontrolled re-identification vectors (§1.5)
- Transport security as a hard requirement, not a recommendation (§2.6)

### 1.5 Re-identification Risk

The absence of free text does not reduce re-identification risk — it increases machine-queryability of the data. A sequence of `location` + `currency` + `status` messages in a thread constitutes a rich behavioural profile of the participants, queryable without any NLP. Operators deploying Swinn for use cases involving natural persons must apply appropriate purpose limitation at the thread level. This document notes the risk explicitly; mitigation is an operator responsibility.

---

## 2. Core Protocol

### 2.1 The Envelope

Every message body is a typed JSON envelope with three required fields:

```json
{
  "type":    "currency",
  "version": "1.0",
  "payload": {
    "amount":        142.50,
    "currency_code": "USD",
    "direction":     "credit"
  }
}
```

No other top-level fields are permitted (`additionalProperties: false` at envelope level). The payload shape is fully defined by the type's JSON Schema.

### 2.2 Write Validation

On every `POST /messages` or append to a thread, the server:

1. Verifies the caller is an active Participant of the target thread. If not: `403 Forbidden`.
2. Verifies the envelope has `type`, `version`, and `payload` fields with no extra top-level fields.
3. Looks up `type` in the registry. If not found: `422 Unprocessable Entity`, body names the unknown type.
4. Validates `payload` against the type's JSON Schema including cross-field constraints. If invalid: `422`, body lists violations.
5. Normalises `recorded_at` timestamps to UTC before storage (if present).
6. Stores the message only on full validation pass.

Rejection is loud and specific. Machine clients are expected to handle `403` and `422` responses gracefully.

### 2.3 Versioning Contract

| Change | Policy |
|--------|--------|
| Patch (1.0.x) | Schema definition fix only. No payload shape change. Old messages remain valid. |
| Minor (1.x.0) | Additive — new optional fields only, drawn from controlled vocabularies. Existing messages remain valid. Clients must tolerate unknown optional fields. |
| Major (x.0.0) | Breaking schema change. Old and new versions coexist in the registry. Clients declare which versions they support. |

### 2.4 Timestamp Normalisation

All `date-time` fields across all types must be submitted in UTC (suffix `Z` or `+00:00`). Non-UTC submissions are rejected with `422`. The server normalises stored timestamps to UTC regardless, but rejection on write is preferred to silent coercion — silent coercion hides client bugs.

**Rationale:** `2026-06-27T14:30:00+05:30` and `2026-06-27T09:00:00Z` are the same instant but different strings. Any system doing temporal ordering, deduplication, or windowed aggregation across messages from multiple clients requires a single canonical form. UTC is that form.

### 2.5 URI Validation Policy

All URI fields (currently `file_reference.url`) are subject to:

- **Scheme:** `https` only. `http`, `ftp`, `file`, and all other schemes are rejected.
- **Private IP blocking:** Requests referencing RFC 1918 ranges (`10.x`, `172.16-31.x`, `192.168.x`), loopback (`127.x`, `::1`), link-local (`169.254.x`), and cloud metadata endpoints (e.g. `169.254.169.254`) are rejected. This prevents Swinn from being used as an SSRF delivery mechanism to clients that auto-fetch referenced URIs.
- **No URL resolution at write time:** Swinn validates and stores the URI. It does not fetch it. Integrity verification (checksum) is the client's responsibility at read time.

### 2.6 Transport Security

HTTPS is required for all API communication. HTTP is not supported. This is a deployment requirement enforced at the infrastructure level and documented here as a hard constraint, not a recommendation. Instances deployed without TLS termination are misconfigured.

"Nothing is encrypted" refers to Swinn's storage model — payloads are stored as plain JSON, not encrypted at rest by default. Operators handling sensitive data categories (location, financial) must apply at-rest encryption at the infrastructure layer. This is an operator responsibility and a known, documented gap in the default configuration.

### 2.7 Rate Limiting

All endpoints are subject to rate limiting. Default limits (configurable per deployment):

| Endpoint | Limit |
|----------|-------|
| `POST /messages` (write) | 60 requests / minute / token |
| `GET /messages` (read) | 120 requests / minute / token |
| `GET /types` (discovery) | 30 requests / minute / IP, unauthenticated |
| All authenticated endpoints | 1000 requests / hour / token (global ceiling) |

Exceeding limits: `429 Too Many Requests` with `Retry-After` header.

### 2.8 Token Lifecycle

Passport OAuth2 tokens are subject to the following policy:

- **Access tokens:** Short-lived. Default expiry 1 hour.
- **Refresh tokens:** Default expiry 30 days. Single-use (rotated on refresh).
- **Machine client tokens (client credentials):** Default expiry 24 hours. Must be explicitly re-issued.
- **Revocation:** Token revocation endpoint is exposed and must be called on client decommission or compromise. Revoked tokens are rejected immediately.
- **Scope:** Token scopes are enforced. A token issued with `read` scope cannot write. Scope definitions are part of the implementation plan (§8, Step 0).

---

## 3. Authorization Model

### 3.1 Thread-Level Access Control

A valid OAuth2 token grants access to the API. It does not grant access to any thread. Access to a thread — read or write — requires the token's associated identity to be an active `Participant` of that thread.

| Operation | Requirement |
|-----------|-------------|
| Read thread messages | Active Participant of the thread |
| Write a message to a thread | Active Participant of the thread |
| Create a new thread | Any valid token |
| Add a participant to a thread | Active Participant of the thread (creator, or any participant — TBD by operator policy) |

A request from a valid token holder who is not a Participant of the target thread returns `403 Forbidden` — not `404`. The thread's existence is not disclosed to non-participants.

### 3.2 No Ambient Read Access

There is no "list all threads" or "list all messages" endpoint accessible to arbitrary token holders. A client can only enumerate threads and messages it is a Participant of. This is enforced at the query layer — all thread and message queries are scoped by the authenticated identity's Participant records.

---

## 4. Type Registry

### 4.1 Type Definition

Each type is a PHP class implementing `MessageType`, living in `app/MessageTypes/`. The class declares:

| Field | Description |
|-------|-------------|
| `name` | Unique slug. `snake_case`. e.g. `currency` |
| `version` | Semver string. e.g. `1.0` |
| `purpose` | One-paragraph plain-English description. What data, why it exists, who uses it. Surfaces in `/types`. |
| `schema()` | Returns a JSON Schema array including all enum constraints and cross-field constraints. Used for write-time validation. |
| `rendererHint()` | Returns a string component name for human clients. e.g. `CurrencyCard` |

Example — closed enums, cross-field constraint, `additionalProperties: false`:

```php
// app/Contracts/MessageType.php
interface MessageType {
    public string $name;
    public string $version;
    public string $purpose;
    public function schema(): array;
    public function rendererHint(): string;
}

// app/MessageTypes/MetricType.php
class MetricType implements MessageType {
    public string $name    = 'metric';
    public string $version = '1.0';
    public string $purpose =
        'A scalar numeric measurement identified by a controlled ' .
        'quantity kind and SI-aligned unit. Cross-field compatibility ' .
        'between quantity and unit is enforced at write time.';

    // Valid quantity → unit pairs. Any combination not listed is rejected.
    public const COMPATIBLE_UNITS = [
        'temperature'     => ['celsius', 'fahrenheit', 'kelvin'],
        'humidity'        => ['percent'],
        'pressure'        => ['hpa', 'bar'],
        'speed'           => ['m_s', 'km_h', 'mph'],
        'distance'        => ['km', 'm', 'cm', 'mm'],
        'mass'            => ['kg', 'g'],
        'energy'          => ['kwh', 'wh'],
        'power'           => ['w', 'kw'],
        'voltage'         => ['v', 'mv'],
        'current'         => ['a', 'ma'],
        'frequency'       => ['hz', 'khz', 'mhz'],
        'luminance'       => ['lux'],
        'co2'             => ['ppm'],
        'pm2_5'           => ['ug_m3'],
        'pm10'            => ['ug_m3'],
        'battery_level'   => ['percent'],
        'signal_strength' => ['dbm'],
    ];

    public function schema(): array {
        return [
            'type'                 => 'object',
            'required'             => ['quantity', 'value', 'unit'],
            'additionalProperties' => false,
            'properties'           => [
                'quantity'    => ['type' => 'string', 'enum' => array_keys(self::COMPATIBLE_UNITS)],
                'value'       => ['type' => 'number'],
                'unit'        => ['type' => 'string', 'enum' => array_unique(array_merge(...array_values(self::COMPATIBLE_UNITS)))],
                'recorded_at' => ['type' => 'string', 'format' => 'date-time'],
            ]
            // Cross-field quantity/unit compatibility is enforced in
            // MetricType::validate(payload) called after JSON Schema validation.
        ];
    }

    public function validate(array $payload): array {
        $errors = [];
        $allowed = self::COMPATIBLE_UNITS[$payload['quantity']] ?? [];
        if (!in_array($payload['unit'], $allowed, true)) {
            $errors[] = "unit '{$payload['unit']}' is not valid for quantity '{$payload['quantity']}'";
        }
        return $errors;
    }

    public function rendererHint(): string { return 'MetricDisplay'; }
}
```

The `MessageType` interface is extended with an optional `validate(array $payload): array` method. Types that have cross-field constraints implement it. The `TypeRegistry` calls it after JSON Schema validation passes.

### 4.2 Discovery Endpoint

`GET /types` — unauthenticated, rate-limited (§2.7). Returns the full registry including enum vocabularies and compatible unit pairs for `metric`.

```json
[
  {
    "type":          "metric",
    "version":       "1.0",
    "purpose":       "A scalar numeric measurement...",
    "schema":        { "..." },
    "renderer_hint": "MetricDisplay",
    "constraints":   {
      "compatible_units": {
        "temperature": ["celsius", "fahrenheit", "kelvin"],
        "humidity":    ["percent"]
      }
    }
  }
]
```

### 4.3 Governance

The registry is closed. Types are added by maintainers via pull request. Every new type requires: a documented purpose, zero free-text fields, cross-field constraints declared where applicable, and `additionalProperties: false`. There is no runtime registration API. MIT license means anyone may fork and maintain a different vocabulary on their own instance.

---

## 5. Starter Type Set

### 5.1 `currency`

**Purpose:** A monetary transaction value. Amount paired with an ISO 4217 currency code and a flow direction. No description, label, or reference field.  
**Renderer hint:** `CurrencyCard`

```json
{
  "type": "currency", "version": "1.0",
  "payload": {
    "amount":        142.50,
    "currency_code": "USD",
    "direction":     "credit"
  }
}
```

Schema:
- `amount` — number, required, minimum `0.00000001` (no zero, no negative values — direction carries sign semantics), maximum `999999999999.99`
- `currency_code` — string, required, pattern `^[A-Z]{3}$`, validated against the full ISO 4217 active currency list
- `direction` — string, required, enum: `credit` | `debit`

> `direction` is required, not optional. An amount without direction is ambiguous data.

---

### 5.2 `location`

**Purpose:** A WGS84 coordinate pair. The fact of where, at a moment in time. No label, name, or place description.  
**Renderer hint:** `LocationPin`

```json
{
  "type": "location", "version": "1.0",
  "payload": {
    "lat":         51.5074,
    "lng":        -0.1278,
    "accuracy_m":  15,
    "recorded_at": "2026-06-27T14:30:00Z"
  }
}
```

Schema:
- `lat` — number, required, minimum -90, maximum 90, precision: maximum 8 decimal places (≈ 1mm resolution — more is false precision)
- `lng` — number, required, minimum -180, maximum 180, precision: maximum 8 decimal places
- `accuracy_m` — integer, optional, minimum 1 — GPS accuracy radius in metres. Absence means accuracy unknown, not perfect.
- `recorded_at` — string, required (not optional for location — a coordinate without a time is not a useful signal), format `date-time`, UTC

> **Privacy note:** Precise coordinates with timestamps are movement data. Operators must apply retention limits appropriate to their legal basis. Default retention policy: see §7.

---

### 5.3 `status`

**Purpose:** A state machine transition. The entity's new state as a slug. Swinn validates slug format; the valid state vocabulary is the sending system's domain.  
**Renderer hint:** `StatusBadge`

```json
{
  "type": "status", "version": "1.0",
  "payload": {
    "state":       "dispatched",
    "previous":    "processing",
    "recorded_at": "2026-06-27T14:30:00Z"
  }
}
```

Schema:
- `state` — string, required, pattern `^[a-z][a-z0-9_]{0,59}$`
- `previous` — string, optional, same pattern
- `recorded_at` — string, required, format `date-time`, UTC

**Security note:** `status` payloads are not authoritative signals. A downstream system must not make access control or financial decisions based solely on a Swinn `status` message. The slug `payment_approved` in a `state` field is not a payment authorisation — it is a notification that something was approved elsewhere. The authoritative record is always in the originating system.

---

### 5.4 `file_reference`

**Purpose:** A durable HTTPS pointer to an external file, with MIME type, byte size, and optional integrity checksum. Swinn carries the reference only.  
**Renderer hint:** `FileCard`

```json
{
  "type": "file_reference", "version": "1.0",
  "payload": {
    "url":              "https://cdn.example.com/a3f9c1.pdf",
    "mime_type":        "application/pdf",
    "size_bytes":       204800,
    "checksum_sha256":  "e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855",
    "expires_at":       "2026-12-31T23:59:59Z"
  }
}
```

Schema:
- `url` — string, required, format `uri`, scheme `https` only, private IP ranges blocked (§2.5)
- `mime_type` — string, required, pattern `^[a-z]+/[a-z0-9.+\-]+$` (IANA format)
- `size_bytes` — integer, required, minimum 1, maximum 10737418240 (10 GB ceiling — references to larger objects must use a different mechanism)
- `checksum_sha256` — string, optional, pattern `^[a-f0-9]{64}$`
- `expires_at` — string, optional, format `date-time`, UTC — signals to clients that the URL will become invalid. Does not trigger any server-side action.

> No `name` field. The URI is the identity. A client may derive a display hint from the URI path segment or `mime_type`. The deploying system controls what goes into the URL — opacity (e.g. UUIDs in paths) is recommended over descriptive filenames.

---

### 5.5 `metric`

**Purpose:** A scalar numeric measurement identified by a controlled quantity kind and SI-aligned unit. Cross-field compatibility between `quantity` and `unit` is enforced at write time.  
**Renderer hint:** `MetricDisplay`

```json
{
  "type": "metric", "version": "1.0",
  "payload": {
    "quantity":    "temperature",
    "value":        22.4,
    "unit":        "celsius",
    "recorded_at": "2026-06-27T14:30:00Z"
  }
}
```

**Compatible quantity/unit pairs (v1) — any other combination is rejected:**

| Quantity | Valid units |
|----------|-------------|
| `temperature` | `celsius` · `fahrenheit` · `kelvin` |
| `humidity` | `percent` |
| `pressure` | `hpa` · `bar` |
| `speed` | `m_s` · `km_h` · `mph` |
| `distance` | `km` · `m` · `cm` · `mm` |
| `mass` | `kg` · `g` |
| `energy` | `kwh` · `wh` |
| `power` | `w` · `kw` |
| `voltage` | `v` · `mv` |
| `current` | `a` · `ma` |
| `frequency` | `hz` · `khz` · `mhz` |
| `luminance` | `lux` |
| `co2` | `ppm` |
| `pm2_5` | `ug_m3` |
| `pm10` | `ug_m3` |
| `battery_level` | `percent` |
| `signal_strength` | `dbm` |

Fields:
- `quantity` — string, required, enum (see table)
- `value` — number, required
- `unit` — string, required, must be compatible with `quantity` (cross-field enforced)
- `recorded_at` — string, required, format `date-time`, UTC

---

## 6. Future Type Vocabulary

Not in v1. Each requires a documented purpose, schema review, zero free-text fields, and cross-field constraints where applicable.

### 6.1 `transit`

A directed journey between two coordinate pairs with a traffic-derived ETA. Distinct from `location` — location is a point, transit is a vector with time. No place name fields. `status` slug restricted to a declared enum: `on_route` | `delayed` | `arrived` | `cancelled`.

### 6.2 Webhook-Connected Types

Machine clients are symmetric participants. Any authorized token holder can push typed envelopes. Candidates with their likely base types:

- **Payment gateways** — `currency` with `direction` likely sufficient
- **Logistics APIs** — `transit` + `status`
- **Monitoring systems** — `metric` + `status`
- **Calendar / scheduling** — new type `event`: `starts_at`, `ends_at` (UTC timestamps), `status` enum
- **Identity / access** — new type `access`: `action` enum (e.g. `granted` | `revoked`), `recorded_at`

### 6.3 Criteria for New Types

- Clear, non-overlapping purpose
- Documented real use case
- Zero free-text fields
- Cross-field constraints declared where applicable
- `additionalProperties: false`
- Privacy note if the type can carry personal data

---

## 7. Data Retention & Erasure

### 7.1 Retention Policy

Default retention: **indefinite** (messages are soft-deleted, not purged, unless the operator configures otherwise). Operators handling personal data (location, financial) must configure a retention limit appropriate to their legal basis. The core application exposes a configurable `MESSAGE_RETENTION_DAYS` environment variable. Messages older than this value are hard-deleted by a scheduled job. Default: `null` (no automatic purge).

### 7.2 Erasure

A data subject erasure request must result in:

1. Hard deletion of all messages authored by the subject across all threads.
2. Removal of the subject from all Participant records.
3. Soft-deletion of threads where the subject was the sole participant.

Erasure does not cascade to other participants' copies — Swinn is a transport, not a ledger. The operator is responsible for coordinating erasure with downstream systems that consumed the data.

### 7.3 Soft Delete Behaviour

A soft-deleted message: the `body` payload is set to `null`, the `deleted_at` timestamp is set, the record is retained for referential integrity. The message appears in thread history as a tombstone — type and timestamp visible, payload absent. This is the only erasure-compatible approach given that message positions in a thread are meaningful to other participants.

---

## 8. Implementation Plan

Ordered and incremental. Each step is independently shippable.

---

### Step 0 — Token Scopes & Authorization Foundation

Define OAuth2 scopes: `threads:read`, `threads:write`, `types:read`. Wire Participant-scoped access checks into the base controller. All subsequent steps assume this is in place.

**Files to create/touch:**
- `app/Http/Middleware/EnforceParticipantAccess.php` — checks authenticated identity is a Participant of the target thread
- `config/passport.php` — define scopes
- `app/Providers/AuthServiceProvider.php` — register scope policies

**Tests:**
- Non-participant token receives `403` on thread read
- Non-participant token receives `403` on thread write
- Thread existence is not disclosed to non-participants (`403`, not `404`)
- Participant token receives correct data

---

### Step 1 — Enforce Envelope Shape

Add structural validation: `body` must be `{ type, version, payload }` with `additionalProperties: false`. Return `422` on failure.

**Files to create/touch:**
- `app/Http/Requests/MessageRequest.php`
- `app/Services/MessageService.php`

**Tests:** 422 on missing/invalid fields, extra top-level fields. 201 on valid envelope.

---

### Step 2 — Build the Registry

Create `MessageType` interface (with optional `validate()` method), five starter type classes, `TypeRegistry` singleton, and `MessageTypeServiceProvider`. Wire JSON Schema validation via `opis/json-schema` plus cross-field `validate()` calls.

**Files to create:**
- `app/Contracts/MessageType.php`
- `app/Services/TypeRegistry.php`
- `app/MessageTypes/CurrencyType.php`
- `app/MessageTypes/LocationType.php`
- `app/MessageTypes/StatusType.php`
- `app/MessageTypes/FileReferenceType.php`
- `app/MessageTypes/MetricType.php`
- `app/Providers/MessageTypeServiceProvider.php`

**Validation behaviour:**
- Unknown type → `422 { "error": "unknown_type", "type": "..." }`
- Schema violation → `422 { "error": "invalid_payload", "violations": [...] }`
- Cross-field violation (metric) → `422 { "error": "invalid_payload", "violations": ["unit 'dbm' is not valid for quantity 'temperature'"] }`
- Private IP in `file_reference.url` → `422 { "error": "invalid_payload", "violations": ["url targets a disallowed address range"] }`
- Non-UTC timestamp → `422 { "error": "invalid_payload", "violations": ["recorded_at must be UTC"] }`

**Tests:** Valid and invalid payloads for all five types. All cross-field and URI constraint cases.

---

### Step 3 — Discovery Endpoint

`GET /types` — unauthenticated, rate-limited. Returns full registry including `constraints.compatible_units` for `metric`.

**Files to create:**
- `app/Http/Controllers/TypeController.php`
- `app/Http/Resources/MessageTypeResource.php`
- Route in `routes/api.php` outside `auth:api`

**Tests:** All five types returned with all required fields. Unauthenticated succeeds. Rate limit enforced.

---

### Step 4 — Renderer Map in Web UI

Fetch `/types` on Vue mount. Dispatch by `renderer_hint`. Components are purely data-driven — enums mapped to display strings via client-side i18n, coordinates resolved to map tiles, no stored text displayed raw.

**Files to create:**
- `resources/js/composables/useTypeRegistry.js`
- `resources/js/components/messages/CurrencyCard.vue`
- `resources/js/components/messages/LocationPin.vue`
- `resources/js/components/messages/StatusBadge.vue`
- `resources/js/components/messages/FileCard.vue`
- `resources/js/components/messages/MetricDisplay.vue`
- `resources/js/components/messages/MessageRenderer.vue`

**Key rendering decisions:**
- `LocationPin` — coordinates to map tile, no stored label
- `StatusBadge` — slug to i18n string, slug never shown raw
- `MetricDisplay` — unit enum to display symbol (°C, %, hPa)
- `FileCard` — display derived from `mime_type` and URI, no stored filename
- `CurrencyCard` — `direction` drives colour/sign, `currency_code` drives locale formatting

---

### Step 5 — Retention Job & Erasure Endpoint

Wire scheduled hard-delete job for `MESSAGE_RETENTION_DAYS`. Implement erasure endpoint for data subject requests.

**Files to create:**
- `app/Console/Commands/PurgeExpiredMessages.php` — scheduled command
- `app/Http/Controllers/ErasureController.php` — `DELETE /users/{id}/data`
- `app/Services/ErasureService.php` — nulls payloads, removes Participant records, tombstones threads

**Tests:**
- Messages older than retention limit are hard-deleted by job
- Erasure nulls all payloads authored by subject
- Erasure removes subject from Participant records
- Tombstone record visible to other participants with null payload

---

*Drop this file in the repo root or `docs/` and hand to Claude Code with: "Implement the steps in SWINN_DESIGN.md in order, starting with Step 0."*
