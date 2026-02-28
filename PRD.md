# Product Requirements Document — Healthcare Referral Management System

> Reverse-engineered from `README.md` and the implemented codebase (routes, models, migrations, actions, config) as of the current `main` branch. This document describes the system **as built**, not a forward-looking spec — treat divergences between this file and the code as the code being authoritative, and flag them for a doc update.

## 1. Purpose

A backend system that lets partner hospitals submit patient referrals electronically, have them triaged (department + urgency) by an AI service, routed to the right clinical staff, and tracked through to completion — with a full audit trail and encrypted patient data, suitable for a healthcare compliance context.

Built as a focused technical exercise under a 3–4 day constraint (see `README.md#trade-offs`); several production concerns (rate limiting, real email delivery, OpenAPI docs, report caching) were consciously deferred rather than overlooked — see [§9 Deferred / Out of Scope](#9-deferred--out-of-scope).

## 2. Actors / Roles

| Actor | Auth mechanism | Access |
|---|---|---|
| **Hospital system** | `X-Hospital-Api-Key` header (hashed key lookup) | Submit referrals only. No visibility into other hospitals' data. |
| **Admin** (staff, `role=admin`) | Sanctum bearer token | Full referral visibility, assignment, cancellation, reporting. |
| **Doctor** (staff, `role=doctor`) | Sanctum bearer token | Own assigned referrals; receives notifications for their department. |
| **Coordinator** (staff, `role=coordinator`) | Sanctum bearer token | Same as Doctor — assigned referrals + department notifications. |

Role capability is encoded in `App\Enums\StaffRole`: only `admin` can manage referrals (`canManageReferrals()`); `doctor` and `coordinator` receive referral notifications (`receivesReferralNotifications()`).

## 3. Core Workflows

### 3.1 Referral submission (hospital → system)

1. Hospital calls `POST /api/v1/hospital/referrals` with patient demographics, ICD-10 codes, clinical notes, and urgency level.
2. System resolves the patient by `national_id` (via HMAC lookup, §6) — reuses an existing patient record or creates one.
3. System computes an idempotency hash over `(hospital_id, patient_id, sorted icd10_codes, urgency_level)`. A duplicate submission with the same hash returns the existing referral rather than creating a new one; changing codes or urgency creates a new referral.
4. Referral is created in `pending` status; a `ReferralSubmitted` event fires.
5. Event listeners (async): queue AI triage (`triage` queue), write an audit log entry.

### 3.2 AI-assisted triage

1. `ProcessAiTriageJob` calls the AI service (via `AiTriageContract` / `AiTriageService`) with `{icd10_codes, clinical_notes, urgency_level}`.
2. AI returns `{department, confidence_score, reasoning}`, persisted to `ai_suggested_department`, `ai_confidence_score`, `ai_input_payload`, `ai_output_payload`, `ai_processed_at`.
3. Referral transitions `pending → triaged`; a `ReferralTriaged` event fires.
4. On failure, the job retries up to 3 times with exponential backoff (5s → 25s → 125s) before failing permanently — protects against transient AI API outages without hammering the upstream service.

### 3.3 Staff notification & department matching

1. On `ReferralTriaged`, `NotifyStaffOnTriageListener` creates `StaffNotification` records for staff whose `department` matches the referral's `ai_suggested_department` (and whose role receives referral notifications).
2. If urgency is `emergency`, an escalation timer is scheduled (§3.5).
3. Staff acknowledge notifications via `PATCH /api/v1/staff/notifications/{id}/acknowledge`, which sets `read_at` and (for the linked referral) is the trigger that satisfies "acknowledged" for escalation purposes.

### 3.4 Assignment

1. Admin assigns a referral to a staff member: `PATCH /api/v1/admin/referrals/{id}/assign`.
2. Enforced by the status machine: only valid from `triaged` (and back from `escalated`). Sets `assigned_staff_id`, transitions to `assigned`, fires `ReferralAssigned`.

### 3.5 Emergency escalation

1. For `urgency_level = emergency`, `UrgencyLevel::escalationWindowSeconds()` (default 120s, `EMERGENCY_ESCALATION_DELAY` env) determines a delay before `EscalateEmergencyReferralJob` runs.
2. When the job executes, it re-checks the referral's **current** status — if it has already moved to `acknowledged` or beyond (or been cancelled), the job silently no-ops. Otherwise the referral transitions to `escalated` (admin visibility) and can be re-assigned.
3. This is a deferred-job check, not a live countdown/webhook — "acknowledged" is defined as the staff member hitting the acknowledge endpoint, not merely receiving the notification.

### 3.6 Cancellation

Admin cancels a referral with a required reason: `PATCH /api/v1/admin/referrals/{id}/cancel`. Valid from any non-final state per the status machine (§4). Cancellation reason is stored on the referral and reflected in the audit trail.

### 3.7 Completion

Referral reaches `completed` from `in_progress`. There is no dedicated "complete" API endpoint in the current route table — this is a state the workflow model supports but that isn't yet exposed via a controller action (see §9).

### 3.8 Audit trail

Every status-relevant event (`ReferralSubmitted`, `ReferralTriaged`, `ReferralAssigned`, `ReferralStatusChanged`) is captured by `LogReferralAuditListener` into `audit_logs`, recording actor (`performed_by`), action, field/old/new value, metadata, IP, and user agent. Admins can view a referral's full audit history via `GET /api/v1/admin/referrals/{id}`.

### 3.9 Reporting

Admin-only aggregated statistics via `GET /api/v1/admin/reports`, filterable by `date_from`/`date_to`. Implemented as direct DB aggregation queries (no caching/materialization at current scale — see §9).

## 4. Referral Status Lifecycle

Single source of truth: `App\Enums\ReferralStatus::allowedTransitions()`.

```
pending → triaged → assigned → acknowledged → in_progress → completed
   ↓         ↓          ↓            ↓
cancelled  cancelled  escalated   cancelled
                          ↓
                      assigned (re-assign after escalation)
                          ↓
                      cancelled
```

- `completed` and `cancelled` are final states (`isFinal()`).
- Any attempted transition not in `allowedTransitions()` throws `InvalidReferralTransitionException`, surfaced as HTTP 422 with the response envelope (§7).

## 5. Data Model

| Entity | Key fields | Notes |
|---|---|---|
| **Hospital** | `name`, `code` (unique), `status` (`active`/`suspended`), `api_key_hash` | API key stored as SHA-256 hash only. |
| **Patient** | `first_name`, `last_name`, `date_of_birth`, `national_id`, `insurance_number` (all encrypted at rest), `national_id_hash` (unique, HMAC-SHA256, plaintext-searchable) | PII fields stored as `text` columns to hold ciphertext. |
| **Staff** | `name`, `email` (unique), `password`, `role` (`admin`/`doctor`/`coordinator`), `department`, `is_available` | Indexed on `(role, department)` and `(role, is_available)` for notification matching. |
| **Referral** | `patient_id`, `hospital_id`, `assigned_staff_id`, `urgency_level`, `status`, `icd10_codes` (json), `clinical_notes`, `department`, `ai_suggested_department`, `ai_confidence_score`, `ai_processed_at`, `ai_input_payload`/`ai_output_payload` (json), `cancellation_reason`, `submitted_hash` (unique) | Indexed on `status`, `urgency_level`, `department`, `created_at`, `(hospital_id, status)`. |
| **StaffNotification** | `staff_id`, `referral_id` (nullable), `message`, `channel` (`email`/`sms`/`in_app`), `sent_at`, `read_at` | Indexed on `(staff_id, read_at)` and `(staff_id, channel)`. |
| **AuditLog** | `referral_id`, `performed_by` (nullable staff), `action`, `field_name`, `old_value`, `new_value`, `metadata` (json), `ip_address`, `user_agent`, `created_at` only (no `updated_at` — append-only) | Indexed on `(referral_id, created_at)` and `performed_by`. |
| **User** | Laravel's default auth table — present but not part of the staff/hospital domain flows described above. | |

Foreign key behavior is intentional: referrals `restrictOnDelete` on `patient`/`hospital` (can't orphan a referral by deleting its patient or hospital), `nullOnDelete` on `assigned_staff_id` (unassigning is safe), audit logs `cascadeOnDelete` on `referral_id` (logs die with the referral) but `nullOnDelete` on `performed_by` (preserve the log if the staff record is removed).

## 6. Security & Compliance Requirements

1. **PII encryption at rest** — `Patient` fields (`first_name`, `last_name`, `date_of_birth`, `national_id`, `insurance_number`) are encrypted at the application layer (AES-256-CBC via `Crypt::encryptString`) through the `EncryptsPii` trait. Even a full database compromise doesn't expose PII without the app key.
2. **Searchable-without-decryption lookup** — `national_id_hash` (HMAC-SHA256, keyed by `PATIENT_ID_HMAC_KEY`) enables patient dedup/lookup without ever decrypting stored data.
3. **PII never in logs** — `Patient` hides PII fields from serialization; `Referral` hides `clinical_notes`, `ai_input_payload`, `ai_output_payload`; encrypted models expose `toSafeArray()` (`{id, type}`) for any logging that needs a safe reference.
4. **Two independent auth surfaces** — hospital API keys (hashed, header-based, stateless) are fully separate from staff Sanctum tokens; a compromised hospital key cannot reach staff/admin endpoints and vice versa.
5. **Single active staff session** — login revokes all prior Sanctum tokens for that staff member. A deliberate security posture for a healthcare context, not a technical limitation — see `README.md` assumption 4 if multi-device support is later required.
6. **Role-based access control** — enforced at the route level via `CheckRole` middleware (`role:admin`, `role:admin,doctor,coordinator`), not scattered through controller logic.
7. **Full audit trail** — every referral state change is attributable to an actor, timestamped, and includes IP/user agent, satisfying a baseline healthcare audit requirement.
8. **API-safe error responses** — no stack traces or internal detail leak through API error responses; all exception → response mapping is centralized in `bootstrap/app.php`.

## 7. API Contract

Base path: `/api/v1` (global prefix, `bootstrap/app.php`).

```
POST   /api/v1/auth/login
POST   /api/v1/auth/refresh                              [auth:sanctum]
POST   /api/v1/auth/logout                                [auth:sanctum]

POST   /api/v1/hospital/referrals                         [hospital.auth]

GET    /api/v1/admin/referrals                            [auth:sanctum, role:admin]
GET    /api/v1/admin/referrals/{id}                        [auth:sanctum, role:admin]
PATCH  /api/v1/admin/referrals/{id}/assign                [auth:sanctum, role:admin]
PATCH  /api/v1/admin/referrals/{id}/cancel                [auth:sanctum, role:admin]
GET    /api/v1/admin/reports                               [auth:sanctum, role:admin]

GET    /api/v1/staff/referrals                             [auth:sanctum, role:admin,doctor,coordinator]
PATCH  /api/v1/staff/notifications/{id}/acknowledge        [auth:sanctum, role:admin,doctor,coordinator]
```

**List filters** (`GET /admin/referrals`): `status`, `urgency`, `department`, `date_from`, `date_to`, `per_page`.
**Report filters** (`GET /admin/reports`): `date_from`, `date_to`.

**Response envelope** — every response, success or failure, follows:

```json
{ "success": true, "message": "...", "data": { ... } }
{ "success": false, "message": "...", "errors": { "field": ["..."] } }
```

## 8. Non-Functional Requirements

| Concern | Implementation |
|---|---|
| **Queue reliability** | 4 named queues (`triage`, `escalations`, `notifications`, `default`) with per-job retry/backoff tuned to failure mode (see PRD §3.2, `CLAUDE.md`). |
| **Idempotency** | Hospital referral submission is idempotent via `submitted_hash`; safe to retry on the hospital's side without creating duplicates. |
| **Consistency** | Referral + patient creation wrapped in a DB transaction (`SubmitReferralAction`). |
| **API versioning** | All routes under `/api/v1`; future breaking changes get a new prefix/route file rather than breaking existing consumers. |
| **Testability** | External dependencies (AI service, queue, events) are behind interfaces/facades so they can be faked/mocked in tests without network calls. |

## 9. Deferred / Out of Scope

Explicitly and deliberately deferred given the project's time constraint (not gaps discovered later):

1. **Real email/SMS delivery** — `SendStaffNotificationJob` has the hook but no wired mail driver.
2. **Rate limiting on the hospital API** — no `throttle:` middleware on `hospital/referrals`; a hospital key currently has no request cap.
3. **OpenAPI/API documentation generation** — no `dedoc/scramble` or equivalent; API contract lives in this PRD + `README.md` + FormRequest/Resource source.
4. **Report caching/materialization** — `/admin/reports` runs live aggregation queries; no Redis TTL cache or scheduled summary table yet.
5. **Dedicated KMS for patient encryption keys** — uses Laravel's `Crypt` facade (app-key-derived); no AWS KMS/Vault-based key rotation independent of deploys.
6. **`completed` status transition endpoint** — the state machine supports `in_progress → completed`, but no controller action currently triggers it.
7. **FHIR R4 payload compatibility**, **event sourcing for referral state**, **WebSocket/broadcast real-time notifications**, **Horizon queue monitoring**, **Supervisor config for production queue workers**, **paginated audit log retrieval** (currently loads all logs for a referral in one response).

## 10. Key Assumptions (carried from README)

1. **Department matching** = free-text string equality between `ai_suggested_department` and staff `department`; no formal department taxonomy.
2. **"Acknowledged"** = the staff member explicitly hitting the acknowledge endpoint, not passive notification delivery.
3. **Idempotency key** = `(hospital_id, patient_id, icd10_codes, urgency_level)`; any other field changing (e.g. clinical notes) on resubmission does *not* create a new referral if those four match.
4. **Single active session per staff member** is a deliberate security posture, reversible if multi-device support becomes a requirement.
5. **AI service contract** is fixed at `{icd10_codes, clinical_notes, urgency_level}` in / `{department, confidence_score, reasoning}` out, isolated behind `AiTriageContract` so the real implementation is swappable.

## 11. Open Questions for Future Work

- What should happen when an AI triage job exhausts all retries (referral currently stays `pending` indefinitely — is manual/human triage fallback required)?
- Multi-department referrals (a referral needing more than one specialty) are not modeled — `department`/`ai_suggested_department` are single strings.

*(Confirmed, not open: `AuthenticateHospital` middleware already rejects non-`active` hospitals with 403 "Hospital account is suspended." before any referral is submitted.)*
