# Swinn — Open Data-Communication Service
## Design Brief · v0.1 · 2026

---

## 1. Vision & Principles

### 1.1 What Swinn Is

WhatsApp for structured data. A thread is a channel. A message is a typed data envelope. Any authorized client — a web UI, a mobile app, a machine process, a webhook receiver — participates on equal footing. There is no privileged client.

The API is the product. The web UI is the reference human client, nothing more.

### 1.2 Principles

| Principle | Description |
|-----------|-------------|
| Data consistency | Every message of a given type has the same shape, everywhere, always. The registry enforces this at write time. |
| Transparency | Schemas and purposes are public, machine-readable, and discoverable via API. No hidden contracts. |
| No free text | Plain text is not a message type. If something needs to be communicated, it has a type and a schema. |
| Symmetric clients | Machine clients and human clients are indistinguishable at the protocol level. Both use the same OAuth2-secured REST API. |
| YAGNI | The registry is closed and curated. New types are added with documented purpose, not speculatively. |
| MIT License | The codebase is fully open. Anyone may fork, extend, or self-host with a different type vocabulary. |

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
  }
}
```

No other top-level fields are permitted. The payload shape is fully defined by the type's JSON Schema.

### 2.2 Write Validation

On every `POST /messages` or append to a thread, the server:

1. Verifies the envelope has `type`, `version`, and `payload` fields.
2. Looks up `type` in the registry. If not found: `422 Unprocessable Entity`, body names the unknown type.
3. Validates `payload` against the type's JSON Schema. If invalid: `422`, body lists schema violations.
4. Stores the message only on full validation pass.

Rejection is loud and specific. Machine clients are expected to handle 422 responses gracefully.

### 2.3 Versioning Contract

| Change | Policy |
|--------|--------|
| Patch (1.0.x) | Schema definition fix only. No payload shape change. Old messages remain valid. |
| Minor (1.x.0) | Additive — new optional fields only. Existing messages remain valid. Clients must tolerate unknown optional fields. |
| Major (x.0.0) | Breaking schema change. Old and new versions coexist in the registry. Clients declare which versions they support. |

---

## 3. Type Registry

### 3.1 Type Definition

Each type is a PHP class implementing `MessageType`, living in `app/MessageTypes/`. The class declares:

| Field | Description |
|-------|-------------|
| `name` | Unique slug. `snake_case`. e.g. `currency` |
| `version` | Semver string. e.g. `1.0` |
| `purpose` | One-paragraph plain-English description. What data, why it exists, who uses it. Surfaces in `/types`. |
| `schema()` | Returns a JSON Schema array. Used for write-time validation. |
| `rendererHint()` | Returns a string component name for human clients. e.g. `CurrencyCard` |

Example:

```php
// app/Contracts/MessageType.php
interface MessageType {
    public string $name;
    public string $version;
    public string $purpose;
    public function schema(): array;
    public function rendererHint(): string;
}

// app/MessageTypes/CurrencyType.php
class CurrencyType implements MessageType {
    public string $name    = 'currency';
    public string $version = '1.0';
    public string $purpose =
        'A monetary value in a specific currency. Used for payment ' .
        'references, invoice notifications, and balance updates.';

    public function schema(): array {
        return [
            'type'       => 'object',
            'required'   => ['amount', 'currency_code'],
            'properties' => [
                'amount'        => ['type' => 'number'],
                'currency_code' => ['type' => 'string', 'pattern' => '^[A-Z]{3}$'],
            ]
        ];
    }

    public function rendererHint(): string {
        return 'CurrencyCard';
    }
}
```

### 3.2 Discovery Endpoint

`GET /types` — unauthenticated. Returns the full registry.

```json
[
  {
    "type":          "currency",
    "version":       "1.0",
    "purpose":       "A monetary value in a specific currency...",
    "schema":        { "..." },
    "renderer_hint": "CurrencyCard"
  }
]
```

Human clients fetch `/types` on startup to build their renderer map. Machine clients use it to discover available data shapes before subscribing to a thread.

### 3.3 Governance

The registry is closed. Types are added by maintainers via pull request. Every new type requires a documented purpose. There is no runtime registration API. MIT license means anyone may fork and maintain a different vocabulary on their own instance.

---

## 4. Starter Type Set

### 4.1 `currency`

**Purpose:** A monetary value in a specific currency. Payment references, invoice notifications, balance updates.  
**Renderer hint:** `CurrencyCard`

```json
{
  "type": "currency", "version": "1.0",
  "payload": {
    "amount":        142.50,
    "currency_code": "USD",
  }
}
```

Schema:
- `amount` — number, required
- `currency_code` — string, required, pattern `^[A-Z]{3}$` (ISO 4217)

---

### 4.2 `location`

**Purpose:** A geographic coordinate. Delivery tracking, check-ins, asset location.  
**Renderer hint:** `LocationPin`

```json
{
  "type": "location", "version": "1.0",
  "payload": {
    "lat":    51.5074,
    "lng":   -0.1278,
  }
}
```

Schema:
- `lat` — number, required, minimum -90, maximum 90
- `lng` — number, required, minimum -180, maximum 180

---

### 4.3 `status`

**Purpose:** A named state transition with an optional reason code. Order status, device state, workflow transitions.  
**Renderer hint:** `StatusBadge`

> **Note:** `reason` must be a `snake_case` slug — no free text. This is intentional. Status is a state machine, not a notes field.

```json
{
  "type": "status", "version": "1.0",
  "payload": {
    "state":  "dispatched",
    "reason": "carrier_collected"
  }
}
```

Schema:
- `state` — string, required, pattern `^[a-z][a-z0-9_]*$`
- `reason` — string, optional, pattern `^[a-z][a-z0-9_]*$`, maxLength 60

---

### 4.4 `file_reference`

**Purpose:** A pointer to an external file. URL, MIME type, and byte size. Swinn carries the reference only — not the file content.  
**Renderer hint:** `FileCard`

```json
{
  "type": "file_reference", "version": "1.0",
  "payload": {
    "url":        "https://cdn.example.com/invoice_4821.pdf",
    "mime_type":  "application/pdf",
    "size_bytes": 204800,
    "name":       "invoice_4821.pdf"
  }
}
```

Schema:
- `url` — string, required, format `uri`
- `mime_type` — string, required
- `size_bytes` — integer, required, minimum 1

---

### 4.5 `metric`

**Purpose:** A named numeric measurement with a unit. Sensor readings, KPIs, any scalar value at a point in time.  
**Renderer hint:** `MetricDisplay`

```json
{
  "type": "metric", "version": "1.0",
  "payload": {
    "name":        "ambient_temperature",
    "value":        22.4,
    "unit":        "celsius",
    "recorded_at": "2026-06-27T14:30:00Z"
  }
}
```

Schema:
- `name` — string, required, pattern `^[a-z][a-z0-9_]*$`
- `value` — number, required
- `unit` — string, required, pattern `^[a-z][a-z0-9_]*$`
- `recorded_at` — string, optional, format `date-time` (ISO 8601)

---

## 5. Future Type Vocabulary

Not in v1. Each requires a documented purpose and schema review before inclusion.

### 5.1 `transit`

A journey between two geographic points with a traffic-derived ETA. Distinct from `location` — location is a point in space, transit is a directed journey with time.

Candidate fields: `origin` (lat/lng/label), `destination` (lat/lng/label), `eta` (ISO 8601), `duration_seconds` (integer), `status` (slug: `on_route` | `delayed` | `arrived`).

### 5.2 Webhook-Connected Types

Machine clients are symmetric participants — any external service with a webhook capability can push typed envelopes to Swinn threads using an authorized access token. Candidates:

- **Payment gateways** — transaction confirmations, refund events, dispute notifications
- **Logistics APIs** — shipment tracking events, carrier status updates
- **Monitoring systems** — alert triggers, threshold breaches, recovery events
- **Calendar / scheduling** — appointment confirmations, rescheduling events
- **Identity / access** — login events, permission changes, access grants

### 5.3 Criteria for New Types

Before a type is added to the core registry:

- Clear, non-overlapping purpose that cannot be served by an existing type
- Documented real use case (not speculative)
- Payload schema with no free-text fields
- Proposed renderer hint for human clients

---

## 6. Implementation Plan

Ordered and incremental. Each step is independently shippable.

---

### Step 1 — Enforce Envelope Shape

Add structural validation to `MessageService`: `body` must be a JSON object with `type` (string), `version` (string), and `payload` (object). No other top-level fields permitted. Return `422` with a clear error body on failure. No registry lookup yet.

**Files to create/touch:**
- `app/Http/Requests/MessageRequest.php` — add envelope validation rules
- `app/Services/MessageService.php` — enforce on store and append

**Tests:**
- 422 on missing `type`
- 422 on missing `version`
- 422 on missing `payload`
- 422 on non-object `payload`
- 422 on extra top-level fields
- 201 on valid minimal envelope

---

### Step 2 — Build the Registry

Create the `MessageType` interface and five starter type classes. Wire a `TypeRegistry` singleton. Add JSON Schema validation on write via a JSON Schema validator package (e.g. `opis/json-schema`).

**Files to create:**
- `app/Contracts/MessageType.php` — interface
- `app/Services/TypeRegistry.php` — singleton, loads all registered types
- `app/MessageTypes/CurrencyType.php`
- `app/MessageTypes/LocationType.php`
- `app/MessageTypes/StatusType.php`
- `app/MessageTypes/FileReferenceType.php`
- `app/MessageTypes/MetricType.php`
- `app/Providers/MessageTypeServiceProvider.php` — registers types into the registry

**Validation behaviour:**
- Unknown `type` → `422 { "error": "unknown_type", "type": "transit" }`
- Schema violation → `422 { "error": "invalid_payload", "violations": [...] }`

**Tests:**
- Each type: valid payload passes
- Each type: missing required field fails with correct violation
- Each type: wrong field type fails
- Unknown type returns `unknown_type` error

---

### Step 3 — Discovery Endpoint

`GET /types` — unauthenticated, no rate limit concern (response is static per deploy).

**Files to create:**
- `app/Http/Controllers/TypeController.php`
- `app/Http/Resources/MessageTypeResource.php`
- Add route to `routes/api.php` (outside `auth:api` middleware)

**Response shape:** array of `{ type, version, purpose, schema, renderer_hint }`

**Tests:**
- Returns all five types
- Each entry contains all required fields
- Unauthenticated request succeeds

---

### Step 4 — Renderer Map in Web UI

Fetch `/types` on Vue app mount. Build renderer map keyed by type name. Each starter type gets a dedicated Vue component.

**Files to create:**
- `resources/js/composables/useTypeRegistry.js` — fetches and caches `/types`
- `resources/js/components/messages/CurrencyCard.vue`
- `resources/js/components/messages/LocationPin.vue`
- `resources/js/components/messages/StatusBadge.vue`
- `resources/js/components/messages/FileCard.vue`
- `resources/js/components/messages/MetricDisplay.vue`
- `resources/js/components/messages/MessageRenderer.vue` — dispatches to correct component by `renderer_hint`

**Renderer dispatch logic:**
```js
// MessageRenderer.vue
const renderers = {
  CurrencyCard:   CurrencyCard,
  LocationPin:    LocationPin,
  StatusBadge:    StatusBadge,
  FileCard:       FileCard,
  MetricDisplay:  MetricDisplay,
}

// Fallback: pretty-printed JSON with type label
// Should not occur in production given the closed registry,
// but kept as a development safety net.
```

**Tests:**
- Each component renders a valid payload without error
- Unknown renderer_hint renders the JSON fallback

---

*Drop this file in the repo root or `docs/` and hand to Claude Code with: "Implement the steps in SWINN_DESIGN.md in order, starting with Step 1."*
