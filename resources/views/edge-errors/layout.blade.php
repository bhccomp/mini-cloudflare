<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
    <style>
        :root {
            color-scheme: dark;
            --bg: #020817;
            --panel: rgba(8, 15, 29, 0.86);
            --panel-border: rgba(148, 163, 184, 0.18);
            --text: #e2e8f0;
            --muted: #94a3b8;
            --line: rgba(148, 163, 184, 0.14);
            --accent: #22d3ee;
            --accent-strong: #67e8f9;
            --accent-soft: rgba(34, 211, 238, 0.18);
            --glow: rgba(34, 211, 238, 0.22);
            --warn: #f59e0b;
            --danger: #fb7185;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: "Segoe UI", ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, sans-serif;
            background:
                radial-gradient(circle at top left, rgba(34, 211, 238, 0.15), transparent 34%),
                radial-gradient(circle at 85% 18%, rgba(14, 165, 233, 0.12), transparent 28%),
                linear-gradient(180deg, #020817 0%, #041427 100%);
            color: var(--text);
            display: grid;
            place-items: center;
            padding: 32px 20px;
        }

        .shell {
            position: relative;
            width: min(1040px, 100%);
        }

        .shell::before,
        .shell::after {
            content: "";
            position: absolute;
            inset: auto;
            pointer-events: none;
            filter: blur(30px);
        }

        .shell::before {
            top: -40px;
            left: -20px;
            width: 180px;
            height: 180px;
            background: rgba(34, 211, 238, 0.12);
        }

        .shell::after {
            right: 0;
            bottom: -30px;
            width: 220px;
            height: 220px;
            background: rgba(56, 189, 248, 0.08);
        }

        .panel {
            position: relative;
            overflow: hidden;
            border-radius: 28px;
            border: 1px solid var(--panel-border);
            background:
                linear-gradient(180deg, rgba(15, 23, 42, 0.96), rgba(8, 15, 29, 0.92)),
                var(--panel);
            box-shadow:
                0 24px 80px rgba(2, 8, 23, 0.48),
                inset 0 1px 0 rgba(255, 255, 255, 0.04);
        }

        .panel::before {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, rgba(34, 211, 238, 0.08), transparent 44%, rgba(34, 211, 238, 0.04));
            pointer-events: none;
        }

        .grid {
            position: relative;
            display: grid;
            grid-template-columns: 1.15fr 0.85fr;
            min-height: 620px;
        }

        .copy,
        .status-card {
            position: relative;
            z-index: 1;
            padding: 40px;
        }

        .copy {
            border-right: 1px solid var(--line);
        }

        .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 8px 14px;
            border-radius: 999px;
            border: 1px solid rgba(34, 211, 238, 0.2);
            background: rgba(34, 211, 238, 0.08);
            color: #a5f3fc;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.2em;
            text-transform: uppercase;
        }

        .eyebrow-dot {
            width: 8px;
            height: 8px;
            border-radius: 999px;
            background: var(--accent);
            box-shadow: 0 0 18px var(--glow);
        }

        h1 {
            margin: 24px 0 14px;
            font-size: clamp(2.2rem, 6vw, 4.6rem);
            line-height: 0.95;
            letter-spacing: -0.04em;
        }

        .lede {
            margin: 0;
            max-width: 34rem;
            color: #cbd5e1;
            font-size: 1.05rem;
            line-height: 1.7;
        }

        .domain {
            margin-top: 22px;
            display: inline-flex;
            align-items: center;
            gap: 12px;
            border-radius: 16px;
            border: 1px solid rgba(148, 163, 184, 0.18);
            background: rgba(15, 23, 42, 0.74);
            padding: 14px 16px;
            color: #e2e8f0;
            font-size: 0.95rem;
        }

        .domain strong {
            color: var(--accent-strong);
            font-weight: 700;
        }

        .list {
            margin: 28px 0 0;
            padding: 0;
            list-style: none;
            display: grid;
            gap: 14px;
        }

        .list li {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            color: #d6e2f0;
            line-height: 1.55;
        }

        .list li::before {
            content: "";
            margin-top: 9px;
            width: 7px;
            height: 7px;
            border-radius: 999px;
            background: var(--accent);
            box-shadow: 0 0 20px var(--glow);
            flex: none;
        }

        .actions {
            margin-top: 32px;
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
        }

        .button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 14px;
            padding: 13px 18px;
            text-decoration: none;
            font-size: 0.95rem;
            font-weight: 700;
        }

        .button-primary {
            background: linear-gradient(135deg, #22d3ee 0%, #67e8f9 100%);
            color: #082032;
            box-shadow: 0 12px 30px rgba(34, 211, 238, 0.18);
        }

        .button-secondary {
            border: 1px solid rgba(148, 163, 184, 0.2);
            color: #dbe7f3;
            background: rgba(15, 23, 42, 0.44);
        }

        .meta {
            margin-top: 18px;
            color: var(--muted);
            font-size: 0.9rem;
            line-height: 1.6;
        }

        .status-card {
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            gap: 24px;
            background:
                radial-gradient(circle at top right, rgba(34, 211, 238, 0.12), transparent 34%),
                linear-gradient(180deg, rgba(4, 20, 39, 0.84), rgba(2, 8, 23, 0.92));
        }

        .status-code {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            width: fit-content;
            border-radius: 999px;
            border: 1px solid rgba(245, 158, 11, 0.18);
            background: rgba(245, 158, 11, 0.08);
            padding: 10px 14px;
            color: #fde68a;
            font-size: 0.82rem;
            font-weight: 700;
            letter-spacing: 0.18em;
            text-transform: uppercase;
        }

        .status-code span {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 56px;
            min-height: 56px;
            border-radius: 18px;
            background: rgba(15, 23, 42, 0.72);
            border: 1px solid rgba(148, 163, 184, 0.18);
            color: #fff7ed;
            font-size: 1.35rem;
            letter-spacing: -0.03em;
        }

        .signal {
            border-radius: 24px;
            border: 1px solid rgba(148, 163, 184, 0.14);
            background: rgba(2, 8, 23, 0.62);
            padding: 24px;
        }

        .signal h2 {
            margin: 0 0 10px;
            font-size: 1.1rem;
            letter-spacing: -0.02em;
        }

        .signal p {
            margin: 0;
            color: #c2d1e1;
            line-height: 1.65;
        }

        .signal + .signal {
            margin-top: 16px;
        }

        .status-foot {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            color: var(--muted);
            font-size: 0.85rem;
        }

        .badge-row {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .badge {
            border-radius: 999px;
            border: 1px solid rgba(148, 163, 184, 0.14);
            background: rgba(15, 23, 42, 0.62);
            padding: 8px 12px;
            color: #d3e1ef;
        }

        @media (max-width: 900px) {
            .grid {
                grid-template-columns: 1fr;
            }

            .copy {
                border-right: 0;
                border-bottom: 1px solid var(--line);
            }
        }

        @media (max-width: 640px) {
            body {
                padding: 18px;
            }

            .copy,
            .status-card {
                padding: 26px 22px;
            }

            .actions {
                flex-direction: column;
            }

            .button {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <main class="shell">
        <section class="panel">
            <div class="grid">
                <div class="copy">
                    <div class="eyebrow">
                        <span class="eyebrow-dot"></span>
                        {{ $eyebrow }}
                    </div>

                    <h1>{{ $headline }}</h1>

                    <p class="lede">{{ $lede }}</p>

                    <div class="domain">
                        <span>Site:</span>
                        <strong>{{ $domainLabel }}</strong>
                    </div>

                    <ul class="list">
                        @foreach ($steps as $step)
                            <li>{{ $step }}</li>
                        @endforeach
                    </ul>

                    <div class="actions">
                        <a href="/" class="button button-primary">{{ $primaryActionLabel }}</a>
                        <a href="/contact" class="button button-secondary">{{ $secondaryActionLabel }}</a>
                    </div>

                    <p class="meta">{{ $footerNote }}</p>
                </div>

                <aside class="status-card">
                    <div>
                        <div class="status-code">
                            <span>{{ $statusCode }}</span>
                            Edge Response
                        </div>

                        <div class="signal" style="margin-top: 22px;">
                            <h2>{{ $sideTitle }}</h2>
                            <p>{{ $sideBody }}</p>
                        </div>

                        <div class="signal">
                            <h2>What you can do next</h2>
                            <p>{{ $recoveryCopy }}</p>
                        </div>
                    </div>

                    <div class="status-foot">
                        <div class="badge-row">
                            @foreach ($badges as $badge)
                                <span class="badge">{{ $badge }}</span>
                            @endforeach
                        </div>
                        <span>FirePhage edge fallback</span>
                    </div>
                </aside>
            </div>
        </section>
    </main>
</body>
</html>
