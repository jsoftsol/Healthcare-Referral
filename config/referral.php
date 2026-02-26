<?php
return [
    'patient_id_hmac_key'         => env('PATIENT_ID_HMAC_KEY', env('APP_KEY')),
    'ai_triage_api_url'           => env('AI_TRIAGE_API_URL'),
    'ai_triage_api_key'           => env('AI_TRIAGE_API_KEY'),
    'ai_triage_timeout'           => (int) env('AI_TRIAGE_TIMEOUT', 30),
    'emergency_escalation_delay'  => (int) env('EMERGENCY_ESCALATION_DELAY', 120),
    'token_expiry_minutes'        => (int) env('SANCTUM_TOKEN_EXPIRY_MINUTES', 60),
    'refresh_token_expiry_days'   => (int) env('SANCTUM_REFRESH_TOKEN_EXPIRY_DAYS', 30),
];
