# Healthcare Referral Management System

**Framework:** Laravel 12  
**Runtime:** PHP 8.5.3

A production-grade Laravel backend for managing patient referrals between hospitals and clinical staff, with AI-assisted triage, event-driven in-app notifications, audit logging, and role-based access control.

---

## Table of Contents

1. [Quick Start](#quick-start)
2. [Architecture & Decisions](#architecture--decisions)
3. [Security Design](#security-design)
4. [API Reference](#api-reference)
5. [Testing Approach](#testing-approach)
6. [Trade-offs](#trade-offs)
7. [What I Would Add With More Time](#what-i-would-add-with-more-time)
8. [Assumptions](#assumptions)

---

## Quick Start

### Option A — Docker (Recommended)

```bash
# 1. Clone and enter directory
git clone https://github.com/jsoftsol/Healthcare-Referral.git && cd Healthcare-Referral

# 2. Copy environment file
cp .env.example .env

# 3. Generate app key (before Docker, so it's available in container)
php artisan key:generate   # or manually set a 32-char base64 key in .env

# 4. Start everything with one command
docker compose up -d

# 5. The app container runs migrations and seeds automatically.
#    Access the API at http://localhost/api/v1
```

After seeding, credentials are printed in the `healthcare_app` container logs:

```
docker compose logs app | grep -A 10 "Seeded"
```

Default seeded credentials:
| Resource | Value |
|---|---|
| Admin login | `admin@healthcare.local` / `password` |
| Hospital 1 API Key | `hsp_seed_hospital_one_key_12345678901` |
| Hospital 2 API Key | `hsp_seed_hospital_two_key_12345678901` |

---

### Option B — Local (without Docker)

**Requirements:** PHP 8.5.3, MySQL 8.0+, Redis 7+, Composer 2

```bash
# 1. Install dependencies
composer install

# 2. Environment setup
cp .env.example .env
php artisan key:generate

# 3. Configure .env — set DB_*, REDIS_*, and generate a PATIENT_ENCRYPTION_KEY:
php artisan tinker --execute="echo base64_encode(random_bytes(32));"
# Paste the output as PATIENT_ENCRYPTION_KEY in .env

# 4. Run migrations and seed
php artisan migrate --seed

# 5. Start queue workers (in separate terminals or use Supervisor in production)
php artisan queue:work --queue=triage,escalations,notifications,default

# 6. Start the development server
php artisan serve
```

### Running Tests

```bash
php artisan test
# or with coverage
php artisan test --coverage
```

Laravel 12 ships with Pest as the default testing framework, so no additional setup is required.

---

## Architecture & Decisions

### Why Action Classes (not fat controllers or fat models)?

I structured business logic into single-responsibility **Action classes** (`app/Actions/`). Each action does exactly one thing:

- `SubmitReferralAction` — creates patient + referral, handles idempotency
- `TriageReferralAction` — calls AI, persists results
- `AssignReferralAction` — validates transition, assigns staff
- `CancelReferralAction` — validates transition, cancels referral
- `EscalateReferralAction` — checks eligibility, escalates and notifies admins

This makes each piece of logic independently testable and means controllers become thin routing/validation delegates — they have no business logic.

I deliberately skipped the Repository pattern. Laravel's Eloquent is already an active record implementation, and adding a repository layer for this domain would be indirection without meaningful benefit. If the system needed to swap out the data store, Eloquent's query builder abstraction already handles that.

### Event-Driven Side Effects

All side effects (audit logging, AI dispatch, staff notifications, escalation scheduling) are decoupled from the core action via **Laravel Events and Listeners**:

```
ReferralSubmitted → DispatchAiTriageListener (queues AI job)
                  → LogReferralAuditListener (writes audit log)

ReferralTriaged   → NotifyStaffOnTriageListener (creates notifications, schedules escalation)
                  → LogReferralAuditListener

ReferralAssigned  → LogReferralAuditListener
ReferralStatusChanged → LogReferralAuditListener
```

This means adding a new side effect (e.g. a webhook to an external EMR system) requires zero changes to existing action or controller code — just a new listener.

### Queue Architecture

Three separate queues with different priority/retry characteristics:

| Queue | Purpose | Retries | Backoff |
|---|---|---|---|
| `triage` | AI processing | 3 | 5s → 25s → 125s (exponential) |
| `escalations` | Emergency escalation timers | 1 | None |
| `notifications` | Staff notifications | 3 | Default |
| `default` | Everything else | 3 | Default |

The AI triage job uses exponential backoff to handle transient API failures gracefully without hammering a struggling upstream service.

### Status Lifecycle

The referral status machine is encoded in the `ReferralStatus` enum itself via `allowedTransitions()`. This means the business rules are co-located with the type, not scattered across multiple services. Any code that needs to check if a transition is valid calls `$status->canTransitionTo($newStatus)` — there's one source of truth.

```
pending → triaged → assigned → acknowledged → in_progress → completed
       ↘         ↘          ↘              ↘             ↘
        cancelled  cancelled  escalated→    cancelled      (final)
                              assigned
```

### API Versioning

All API routes live under `/api/v1/`, configured globally in `bootstrap/app.php` using:

```php
->withRouting(
    api: __DIR__.'/../routes/api.php',
    apiPrefix: 'api/v1',
)
```

Laravel 12 automatically applies this prefix to all routes defined in `routes/api.php`, ensuring consistent versioning without repeating prefixes in route files.

This approach allows future versions (e.g. `/api/v2`) to be introduced by changing the prefix or loading additional route files.


---

## Security Design

### Two Authentication Systems

The system has two distinct auth surfaces:

1. **Hospital API Key** — stateless, via `X-Hospital-Api-Key` header. Keys are stored as SHA-256 hashes only (never plaintext). Verified by hashing the provided key and performing a constant-time comparison via database lookup. Handled by `AuthenticateHospital` middleware.

2. **Staff JWT/Sanctum** — token-based with explicit expiry (`SANCTUM_TOKEN_EXPIRY_MINUTES`, default 60 min) and a separate refresh token. On fresh login, all existing tokens are revoked (single active session). Handled by Laravel Sanctum.

### Patient PII Encryption at Rest

Patient identifiable fields (`first_name`, `last_name`, `date_of_birth`, `national_id`, `insurance_number`) are **encrypted at the application level** using Laravel's `Crypt::encryptString()` (AES-256-CBC) via the `EncryptsPii` trait. This means:

- Even if the database is compromised, PII is unreadable without the application key.
- The `national_id` column stores ciphertext, but a **separate `national_id_hash` column** stores an HMAC-SHA256 hash used for patient lookups — this enables deduplication without ever decrypting.

```php
// Lookup patient by national_id without decryption:
$hash = hash_hmac('sha256', $nationalId, config('referral.patient_id_hmac_key'));
Patient::where('national_id_hash', $hash)->first();
```

### PII Never in Logs

- The `Patient` model overrides `$hidden` to exclude all PII fields from serialization.
- The `Referral` model hides `clinical_notes`, `ai_input_payload`, and `ai_output_payload`.
- Global exception handling is configured in `bootstrap/app.php` using `->withExceptions()` to ensure API-safe error responses.
- A `toSafeArray()` method on `EncryptsPii` models returns only `{id, type}` for logging.

Laravel 12 removes the legacy `app/Exceptions/Handler.php` requirement when exceptions are fully configured via `bootstrap/app.php`.


### Role-Based Access

The `CheckRole` middleware (aliased `role`) enforces access at the route level:
- `role:admin` — admin-only endpoints
- `role:admin,doctor,coordinator` — all authenticated staff

---

## API Reference

Base path for every route below: `/api/v1`. All 11 endpoints follow the response envelope described at the end of this section.

### Authentication

#### `POST /auth/login` — public

| Field | Type | Rules |
|---|---|---|
| `email` | string | required, valid email |
| `password` | string | required |

Response `data`: `staff {id, name, email, role, department}`, `access_token`, `refresh_token`, `token_type`, `expires_at`.

#### `POST /auth/refresh` — `Authorization: Bearer <access_token>`

No body. Revokes the current access token and issues a new one. Response `data`: `access_token`, `expires_at`.

#### `POST /auth/logout` — `Authorization: Bearer <access_token>`

No body. Revokes all tokens for the authenticated staff member (single active session). Response `data`: `null`.

### Hospital — `X-Hospital-Api-Key` header

#### `POST /hospital/referrals`

| Field | Type | Rules |
|---|---|---|
| `patient.first_name` | string | required, max 100 |
| `patient.last_name` | string | required, max 100 |
| `patient.date_of_birth` | date | required, must be before today |
| `patient.national_id` | string | required, max 50 |
| `patient.insurance_number` | string | required, max 50 |
| `urgency_level` | string enum | required — `routine`, `urgent`, `emergency` |
| `icd10_codes` | array\<string\> | required, min 1 item, each matching `^[A-Z][0-9]{2}(\.[0-9A-Z]{1,4})?$` (e.g. `I21`, `I21.0`) |
| `clinical_notes` | string | required, min 10 chars |
| `department` | string \| null | optional, max 100 |

Idempotent: resubmitting the same `(hospital_id, patient_id, icd10_codes, urgency_level)` combination returns the existing referral instead of creating a duplicate. Response `data`: `ReferralResource` (see shape below), HTTP 201.

### Admin — `Authorization: Bearer <token>`, `role:admin`

#### `GET /admin/referrals`

| Query param | Type | Rules |
|---|---|---|
| `status` | string enum | optional — `pending`, `triaged`, `assigned`, `acknowledged`, `in_progress`, `completed`, `cancelled`, `escalated` |
| `urgency` | string enum | optional — `routine`, `urgent`, `emergency` |
| `department` | string | optional, max 100 |
| `date_from` | date | optional |
| `date_to` | date | optional, must be on/after `date_from` |
| `per_page` | integer | optional, 1–100 (default 20) |

Response `data`: paginated collection of `ReferralResource`.

#### `GET /admin/referrals/{id}`

No params. Returns a single `ReferralResource` with `patient`, `hospital`, `assigned_staff`, and full `audit_history` loaded.

#### `PATCH /admin/referrals/{id}/assign`

| Field | Type | Rules |
|---|---|---|
| `staff_id` | integer | required, must exist in `staff` table |

Valid only from `triaged` or `escalated` status. Response `data`: updated `ReferralResource`.

#### `PATCH /admin/referrals/{id}/cancel`

| Field | Type | Rules |
|---|---|---|
| `reason` | string | required, 10–500 chars |

Valid from any non-final status. Response `data`: updated `ReferralResource`.

#### `GET /admin/reports`

| Query param | Type | Rules |
|---|---|---|
| `date_from` | date | optional (default: 30 days ago) |
| `date_to` | date | optional (default: today) |

Response `data`: `period {from, to}`, `total_referrals`, `referrals_per_day` (map of date → count), `average_ai_confidence`, `escalation_rate`, `cancellation_rate`, `escalated_count`, `cancelled_count`.

### Staff — `Authorization: Bearer <token>`, `role:admin,doctor,coordinator`

#### `GET /staff/referrals`

No params. Returns the authenticated staff member's assigned referrals, paginated (20/page). Response `data`: paginated collection of `ReferralResource`.

#### `PATCH /staff/notifications/{id}/acknowledge`

No body. Marks the notification as read (only if it belongs to the authenticated staff member — 404 otherwise); this is the trigger that satisfies "acknowledged" for emergency escalation. Response `data`: `NotificationResource {id, message, channel, referral_id, sent_at, read_at, is_read}`.

### `ReferralResource` shape

Returned by every referral endpoint above.

| Field | Notes |
|---|---|
| `id`, `status`, `urgency_level`, `department`, `icd10_codes` | core referral fields |
| `hospital` | `{id, name, code}` |
| `assigned_staff` | `{id, name, department}` — only when loaded |
| `ai_triage` | `{suggested_department, confidence_score, processed_at}` — only once AI triage has run |
| `cancellation_reason` | null unless cancelled |
| `created_at`, `updated_at` | ISO 8601 |
| `patient` | `{id, first_name, last_name, date_of_birth, insurance_number}` — decrypted on read, only when loaded |
| `audit_history` | array of `{id, action, field_name, old_value, new_value, metadata, performed_by, created_at}` — only when loaded |

### Consistent Response Envelope

All responses follow this structure:

```json
{
  "success": true,
  "message": "Human-readable message",
  "data": { ... }
}
```

Errors:

```json
{
  "success": false,
  "message": "Validation failed.",
  "errors": {
    "urgency_level": ["The urgency level field is required."]
  }
}
```

---

## Testing Approach

I used **Pest PHP** for its expressive syntax and dataset support. Tests are organized into:

- `tests/Unit/Actions/` — Tests for business logic in isolation. External dependencies (AI service) are mocked with Mockery. No database needed for pure logic tests.
- `tests/Feature/Api/` — HTTP-level tests using `RefreshDatabase`. These test the full request/response cycle including auth, validation, and side effects.

**Philosophy:** I test *behaviour*, not implementation. Tests assert outcomes (what changed in the database, what events were dispatched, what HTTP response was returned) rather than internal method calls.

**External dependencies are always faked or mocked:**
- `Queue::fake()` — prevents actual jobs from running in feature tests
- `Event::fake()` — allows asserting events were dispatched
- `Mockery::mock(AiTriageService::class)` — unit tests the action without real HTTP calls

---

## Trade-offs

**Given the 3–4 day constraint, I made the following deliberate decisions:**

1. **Mail sending is stubbed.** `SendStaffNotificationJob` has a hook for email/SMS but doesn't wire up a real mail driver. The pattern is there; plugging in `Mail::to()->queue(new Mailable)` is a 5-minute addition. Adding full Mailable classes with templates would be a distraction from architecture evaluation.

2. **No OpenAPI spec generated.** I would use `dedoc/scramble` to auto-generate from the FormRequest and Resource classes — it's a single package install. Omitted to stay focused on the core system.

3. **Reporting is direct DB queries.** For the scale implied by this assessment, direct aggregation queries are appropriate. At higher scale I would cache these reports (Redis TTL) or materialise them with a scheduled job into a `daily_report_summaries` table.

4. **No rate limiting on hospital API.** In production, I'd add `throttle:60,1` per API key using Redis-backed rate limiting. Left out to keep the scope focused.

5. **Patient PII encryption uses Laravel's `Crypt` facade.** In a real healthcare system, I would evaluate a dedicated KMS (AWS KMS, HashiCorp Vault) for key management so the encryption key rotation is separated from application deployments.

---

## What I Would Add With More Time

- **FHIR R4-compatible payload format** — a `FhirReferralTransformer` that maps the internal model to a FHIR `ServiceRequest` resource would be straightforward to add given the clean data model.
- **Event sourcing for referral state** — Laravel's built-in event system gets us audit logging, but true event sourcing (storing events as the source of truth rather than the current state) would give us full replay capability and point-in-time reconstruction. I'd use the `spatie/laravel-event-sourcing` package.
- **WebSocket real-time notifications** — broadcasting `ReferralAssigned` / `ReferralTriaged` events over Laravel Echo + Soketi for dashboard updates.
- **OpenAPI documentation** — auto-generated via `dedoc/scramble`.
- **Database encryption at the column level** — using MySQL's native column encryption or a Vault-managed encryption as an additional layer on top of application-level encryption.
- **Supervisor configuration** — for managing queue workers and the scheduler in production.
- **Horizon** — Laravel Horizon for queue monitoring, retry management, and visibility into AI triage job throughput.
- **Comprehensive pagination on audit logs** — the current implementation loads all audit logs for a referral. At high volume, this should be paginated separately.

---

## Assumptions

1. **Department matching for notifications** — The spec says "a cardiologist should only receive cardiac referrals." I interpreted this as department-based matching: the AI suggests a department, and staff with `department = ai_suggested_department` receive notifications. The spec doesn't define department taxonomy, so I used free-text strings (consistent with the data model described).

2. **"Acknowledged" as the escalation reset point** — The spec says escalation triggers if not acknowledged within 2 minutes. I interpret "acknowledged" as the staff member explicitly acknowledging the notification (the `acknowledge` endpoint), which transitions the referral to `acknowledged` status. The `EscalateEmergencyReferralJob` checks current status on execution — if it's already been acknowledged or progressed, it silently exits.

3. **Idempotent submission hash** — Duplicate detection uses a hash of `(hospital_id, patient_id, icd10_codes, urgency_level)`. This means the same hospital can re-submit the same clinical situation for a patient without creating duplicates. A re-submission with different codes or urgency creates a new referral.

4. **Single active session per staff member** — On login, existing tokens are revoked. This is a security posture choice (suitable for healthcare). If multi-device access is needed, this can be changed.

5. **AI API contract** — The external AI service is assumed to accept `{icd10_codes, clinical_notes, urgency_level}` and return `{department, confidence_score, reasoning}`. The service layer (`AiTriageService`) isolates this contract so the implementation can be swapped without touching business logic.
