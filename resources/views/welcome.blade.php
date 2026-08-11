<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name', 'Healthcare Referral Management System') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800" rel="stylesheet" />

        <style>
            :root {
                --ink: #0f172a;
                --muted: #64748b;
                --line: #e2e8f0;
                --bg: #f4f7f9;
                --card: #ffffff;
                --accent: #0f766e;
                --accent-dark: #115e59;
                --accent-soft: #ecfdf9;
                --accent-blue: #1d4ed8;
            }

            * { box-sizing: border-box; }

            html, body {
                margin: 0;
                height: 100%;
                background: var(--bg);
                color: var(--ink);
                font-family: 'Inter', ui-sans-serif, system-ui, -apple-system, 'Segoe UI', sans-serif;
                -webkit-font-smoothing: antialiased;
            }

            body {
                background:
                    radial-gradient(1200px 500px at 15% -10%, #d9f2ee 0%, transparent 55%),
                    radial-gradient(900px 500px at 105% 10%, #dbe6fb 0%, transparent 55%),
                    var(--bg);
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 2.5rem 1.5rem;
            }

            .frame {
                width: 100%;
                max-width: 1080px;
            }

            .topbar {
                display: flex;
                align-items: center;
                justify-content: space-between;
                margin-bottom: 2rem;
            }

            .brand {
                display: flex;
                align-items: center;
                gap: 0.65rem;
                font-weight: 700;
                font-size: 1.05rem;
                letter-spacing: -0.01em;
            }

            .brand .mark {
                width: 34px;
                height: 34px;
                border-radius: 9px;
                background: linear-gradient(135deg, var(--accent) 0%, var(--accent-blue) 100%);
                display: flex;
                align-items: center;
                justify-content: center;
                flex-shrink: 0;
            }

            .tag {
                font-size: 0.75rem;
                font-weight: 600;
                letter-spacing: 0.06em;
                text-transform: uppercase;
                color: var(--accent-dark);
                background: var(--accent-soft);
                border: 1px solid #bfe8e1;
                padding: 0.3rem 0.7rem;
                border-radius: 999px;
            }

            .hero {
                text-align: left;
                margin-bottom: 2.25rem;
            }

            .hero h1 {
                font-size: 2.5rem;
                line-height: 1.12;
                letter-spacing: -0.02em;
                margin: 0 0 0.75rem;
                font-weight: 800;
                max-width: 720px;
            }

            .hero p {
                font-size: 1.1rem;
                color: var(--muted);
                margin: 0 0 1.25rem;
                max-width: 620px;
                line-height: 1.55;
            }

            .badges {
                display: flex;
                flex-wrap: wrap;
                gap: 0.5rem;
            }

            .badge {
                font-size: 0.8rem;
                font-weight: 600;
                color: #334155;
                background: var(--card);
                border: 1px solid var(--line);
                padding: 0.4rem 0.8rem;
                border-radius: 999px;
            }

            .grid {
                display: grid;
                grid-template-columns: repeat(3, 1fr);
                gap: 0.9rem;
            }

            .card {
                background: var(--card);
                border: 1px solid var(--line);
                border-radius: 14px;
                padding: 1.1rem 1.15rem;
                box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
            }

            .card .icon {
                width: 30px;
                height: 30px;
                border-radius: 8px;
                background: var(--accent-soft);
                display: flex;
                align-items: center;
                justify-content: center;
                margin-bottom: 0.65rem;
            }

            .card h3 {
                margin: 0 0 0.3rem;
                font-size: 0.92rem;
                font-weight: 700;
                letter-spacing: -0.005em;
            }

            .card p {
                margin: 0;
                font-size: 0.82rem;
                color: var(--muted);
                line-height: 1.45;
            }

            .footer {
                margin-top: 2rem;
                display: flex;
                align-items: center;
                justify-content: space-between;
                font-size: 0.8rem;
                color: var(--muted);
                border-top: 1px solid var(--line);
                padding-top: 1.1rem;
            }

            .footer .dot {
                color: #cbd5e1;
                margin: 0 0.5rem;
            }
        </style>
    </head>
    <body>
        <div class="frame">
            <div class="topbar">
                <div class="brand">
                    <span class="mark">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M12 3v6M9 6h6M4 13c0 4.5 3.5 7.5 8 8 4.5-.5 8-3.5 8-8v-4l-8-3-8 3v4z" stroke="white" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </span>
                    <span>Healthcare Referral Management System</span>
                </div>
                <span class="tag">API Backend</span>
            </div>

            <div class="hero">
                <h1>Patient referrals, triaged and routed automatically.</h1>
                <p>
                    An API-only Laravel backend connecting hospitals and clinical staff &mdash;
                    with AI-assisted triage, event-driven notifications, and full audit logging.
                </p>
                <div class="badges">
                    <span class="badge">Laravel 12</span>
                    <span class="badge">PHP 8.5</span>
                    <span class="badge">MySQL</span>
                    <span class="badge">Redis</span>
                    <span class="badge">Sanctum</span>
                </div>
            </div>

            <div class="grid">
                <div class="card">
                    <span class="icon">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 2l2.4 6.6L21 11l-6.6 2.4L12 20l-2.4-6.6L3 11l6.6-2.4L12 2z" stroke="#0f766e" stroke-width="1.6" stroke-linejoin="round"/></svg>
                    </span>
                    <h3>AI-Assisted Triage</h3>
                    <p>Referrals are automatically analyzed and routed to the right clinical department.</p>
                </div>
                <div class="card">
                    <span class="icon">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="6" cy="6" r="2.5" stroke="#0f766e" stroke-width="1.6"/><circle cx="18" cy="6" r="2.5" stroke="#0f766e" stroke-width="1.6"/><circle cx="12" cy="18" r="2.5" stroke="#0f766e" stroke-width="1.6"/><path d="M8.2 7.2L15.8 7.2M7.5 8.3L11 16M16.5 8.3L13 16" stroke="#0f766e" stroke-width="1.6"/></svg>
                    </span>
                    <h3>Event-Driven Architecture</h3>
                    <p>Audit logging, notifications, and escalation are decoupled side effects, not inline logic.</p>
                </div>
                <div class="card">
                    <span class="icon">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><rect x="5" y="10" width="14" height="10" rx="2" stroke="#0f766e" stroke-width="1.6"/><path d="M8 10V7a4 4 0 0 1 8 0v3" stroke="#0f766e" stroke-width="1.6"/></svg>
                    </span>
                    <h3>PII Encryption at Rest</h3>
                    <p>Patient data is AES-256 encrypted at the application layer, never logged in plaintext.</p>
                </div>
                <div class="card">
                    <span class="icon">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 3l7 3v6c0 4.5-3 7.5-7 9-4-1.5-7-4.5-7-9V6l7-3z" stroke="#0f766e" stroke-width="1.6" stroke-linejoin="round"/></svg>
                    </span>
                    <h3>Dual Auth System</h3>
                    <p>Stateless hospital API keys plus staff Sanctum tokens, each scoped to its own routes.</p>
                </div>
                <div class="card">
                    <span class="icon">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M4 12a8 8 0 1 1 3 6.2" stroke="#0f766e" stroke-width="1.6" stroke-linecap="round"/><path d="M4 8v4h4" stroke="#0f766e" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </span>
                    <h3>Idempotent Submissions</h3>
                    <p>Duplicate referral submissions are detected and merged via content hashing.</p>
                </div>
                <div class="card">
                    <span class="icon">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M6 3h9l3 3v15H6V3z" stroke="#0f766e" stroke-width="1.6" stroke-linejoin="round"/><path d="M9 11h6M9 15h6" stroke="#0f766e" stroke-width="1.6" stroke-linecap="round"/></svg>
                    </span>
                    <h3>Full Audit Trail</h3>
                    <p>Every status transition and assignment is logged for compliance and traceability.</p>
                </div>
            </div>

            <div class="footer">
                <span>github.com/jsoftsol/Healthcare-Referral</span>
                <span>Laravel 12<span class="dot">&middot;</span>PHP 8.5<span class="dot">&middot;</span>Pest</span>
            </div>
        </div>
    </body>
</html>
