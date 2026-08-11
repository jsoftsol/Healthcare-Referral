# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

Laravel 12 / PHP 8.5 API backend for managing patient referrals between hospitals and clinical staff, with AI-assisted triage, event-driven notifications, and audit logging. No frontend beyond the default Vite/Tailwind scaffold — this is an API-only system (`routes/api.php`, prefixed globally with `/api/v1`).

## Documentation map

This file covers *how to build and navigate the code*. Product-level detail (workflows, data model, API contract, security requirements, deferred scope, open questions) lives in **`PRD.md`** at the repo root — read it on demand rather than assuming its content is already in context, e.g. before: designing a new endpoint or workflow, changing the referral status machine, touching PII/encryption handling, or deciding whether something is a genuine gap vs. deliberately deferred scope (PRD §9). Don't duplicate PRD content back into this file — link to it instead, to keep this file's always-loaded footprint small.

## Session memory

Durable project context and standing instructions (why certain scope was cut, git history notes, commit conventions) live in `.claude/memory/` in this repo — not in the global `~/.claude` memory store, and gitignored so they stay local-only — but still load automatically here via these imports:

@.claude/memory/project-origin.md
@.claude/memory/history-rewrite.md
@.claude/memory/no-coauthor.md

Add new durable context as a new file in `.claude/memory/` and reference it above, rather than writing it to the global memory store. See `.claude/memory/README.md` for the index.

## Commands

```bash
# Install & setup
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed

# Run the app (server + queue listener + logs + vite, concurrently)
composer dev

# Queue workers (four named queues — see Architecture below)
php artisan queue:work --queue=triage,escalations,notifications,default

# Tests
php artisan test                              # full suite (Pest)
php artisan test --coverage
php artisan test --filter=SubmitReferralTest  # single test file/case
php artisan test tests/Unit/Actions/TriageReferralActionTest.php

# Lint / format (Laravel Pint) — CI runs this with --test
./vendor/bin/pint
./vendor/bin/pint --test
```

Tests run against SQLite (`database/testing.sqlite`, configured in `phpunit.xml`) with `QUEUE_CONNECTION=sync` and `CACHE_STORE=array`. CI (`.github/workflows/ci.yml`) instead runs against real MySQL + Redis services, then runs `pint --test`.

## Architecture

### Action classes, not fat controllers/models

Business logic lives in single-purpose classes under `app/Actions/Referral/` (`SubmitReferralAction`, `TriageReferralAction`, `AssignReferralAction`, `CancelReferralAction`, `EscalateReferralAction`). Controllers are thin — they validate via `FormRequest` classes (`app/Http/Requests/`) and delegate. There is deliberately no repository layer; Eloquent is used directly.

### Event-driven side effects

Core actions fire events; all side effects (audit logging, AI dispatch, notifications, escalation scheduling) live in listeners (`app/Listeners/`), not in the actions themselves:

```
ReferralSubmitted     → DispatchAiTriageListener (queues ProcessAiTriageJob)
                       → LogReferralAuditListener
ReferralTriaged       → NotifyStaffOnTriageListener (creates notifications, schedules escalation)
                       → LogReferralAuditListener
ReferralAssigned      → LogReferralAuditListener
ReferralStatusChanged → LogReferralAuditListener
```

Adding a new side effect means adding a listener in `app/Providers/EventServiceProvider.php` — never touch the action/controller.

### Referral status machine

`app/Enums/ReferralStatus.php` is the single source of truth for valid transitions via `allowedTransitions()` / `canTransitionTo()`. Invalid transitions throw `InvalidReferralTransitionException`, mapped to a 422 JSON response in `bootstrap/app.php`.

```
pending → triaged → assigned → acknowledged → in_progress → completed
       ↘         ↘          ↘              ↘
        cancelled  cancelled  escalated→assigned   cancelled
```

`EscalateEmergencyReferralJob` re-checks the referral's current status when it runs; if it has already been acknowledged/progressed, it silently no-ops (escalation is a delayed job, not a hard timer).

### Queues

Four named queues with distinct retry/backoff behavior, configured per-job (not globally):

| Queue | Purpose | Retries | Backoff |
|---|---|---|---|
| `triage` | `ProcessAiTriageJob` | 3 | 5s → 25s → 125s |
| `escalations` | `EscalateEmergencyReferralJob` | 1 | none |
| `notifications` | `SendStaffNotificationJob` | 3 | default |
| `default` | everything else | 3 | default |

`AiTriageService` implements `App\Contracts\AiTriageContract` — the external AI API contract is `{icd10_codes, clinical_notes, urgency_level}` in, `{department, confidence_score, reasoning}` out. Mock this contract in unit tests rather than the concrete service.

### Two auth systems

1. **Hospital API key** (`X-Hospital-Api-Key` header) — stateless, verified by `AuthenticateHospital` middleware (aliased `hospital.auth`). Keys are stored as SHA-256 hashes only, never plaintext.
2. **Staff Sanctum tokens** — `auth:sanctum` + `role:*` middleware (`CheckRole`, aliased `role`). Login revokes all prior tokens for that user (single active session by design). Token expiry is configured via `config/referral.php` (`SANCTUM_TOKEN_EXPIRY_MINUTES`, `SANCTUM_REFRESH_TOKEN_EXPIRY_DAYS`), not Sanctum's own config.

Route groups in `routes/api.php` map directly to these: `auth/*` (public + sanctum), `hospital/*` (hospital.auth), `admin/*` (sanctum + role:admin), `staff/*` (sanctum + role:admin,doctor,coordinator).

### Patient PII encryption

`app/Traits/EncryptsPii.php` is applied to models with sensitive fields (currently `Patient`). On `creating`/`updating`, listed `piiFields` are transparently encrypted with `Crypt::encryptString()` (AES-256-CBC) on write and decrypted on `getAttribute()` read — callers never see ciphertext. `national_id` additionally maintains a parallel `national_id_hash` (HMAC-SHA256, keyed by `config('referral.patient_id_hmac_key')`) so patients can be looked up (`Patient::findByNationalId()`) without decrypting anything. When adding a new encrypted field, add it to the model's `$piiFields` array and make sure it's also excluded from `$hidden` handling appropriately — encrypted fields should never appear in logs or serialized output. Models using this trait expose `toSafeArray()` (`{id, type}` only) for safe logging.

### Idempotent referral submission

`SubmitReferralAction` hashes `(hospital_id, patient_id, sorted icd10_codes, urgency_level)` into `submitted_hash`; a matching hash on resubmission returns the existing referral instead of creating a duplicate. Changing codes or urgency creates a new referral.

### Response envelope & error handling

All API responses follow `{success, message, data}` (or `{success, message, errors}` on failure). This is enforced centrally in `bootstrap/app.php` via `->withExceptions()` — there is no `app/Exceptions/Handler.php` (Laravel 12 pattern). When adding a new exception type that should produce a specific API shape, register it there rather than catching it ad hoc in a controller.

## Testing conventions

- `tests/Unit/Actions/` — action logic in isolation, no database dependency where avoidable; external services (`AiTriageContract`) mocked with Mockery.
- `tests/Feature/Api/` — full HTTP request/response cycle with `RefreshDatabase`, asserting on database state, dispatched events (`Event::fake()`), and queued jobs (`Queue::fake()`) — not on internal method calls.
- Pest is configured (`tests/Pest.php`) to apply `Tests\TestCase` to both `Feature` and `Unit` suites.
