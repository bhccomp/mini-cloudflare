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
            padding: 18px 18px 22px;
        }

        .shell {
            position: relative;
            width: min(1280px, 100%);
            margin: 0 auto;
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
            border-radius: 24px;
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
            grid-template-columns: minmax(0, 1.42fr) minmax(320px, 0.58fr);
            min-height: 0;
        }

        .copy,
        .status-card {
            position: relative;
            z-index: 1;
            padding: 28px;
        }

        .copy {
            border-right: 1px solid var(--line);
        }

        .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 7px 13px;
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
            margin: 16px 0 10px;
            font-size: clamp(1.9rem, 4.2vw, 3.7rem);
            line-height: 1;
            letter-spacing: -0.04em;
        }

        .lede {
            margin: 0;
            max-width: 44rem;
            color: #cbd5e1;
            font-size: 0.98rem;
            line-height: 1.6;
        }

        .domain {
            margin-top: 18px;
            display: inline-flex;
            align-items: center;
            gap: 12px;
            border-radius: 16px;
            border: 1px solid rgba(148, 163, 184, 0.18);
            background: rgba(15, 23, 42, 0.74);
            padding: 12px 15px;
            color: #e2e8f0;
            font-size: 0.92rem;
        }

        .domain strong {
            color: var(--accent-strong);
            font-weight: 700;
        }

        .hostname-value {
            word-break: break-word;
        }

        .list {
            margin: 18px 0 0;
            padding: 0;
            list-style: none;
            display: grid;
            gap: 10px;
        }

        .list li {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            color: #d6e2f0;
            line-height: 1.48;
            font-size: 0.96rem;
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
            margin-top: 20px;
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
        }

        .button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 14px;
            padding: 12px 17px;
            text-decoration: none;
            font-size: 0.92rem;
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
            margin-top: 14px;
            color: var(--muted);
            font-size: 0.86rem;
            line-height: 1.55;
        }

        .status-card {
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            gap: 14px;
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
            padding: 9px 13px;
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
            min-width: 50px;
            min-height: 50px;
            border-radius: 16px;
            background: rgba(15, 23, 42, 0.72);
            border: 1px solid rgba(148, 163, 184, 0.18);
            color: #fff7ed;
            font-size: 1.2rem;
            letter-spacing: -0.03em;
        }

        .signal {
            border-radius: 24px;
            border: 1px solid rgba(148, 163, 184, 0.14);
            background: rgba(2, 8, 23, 0.62);
            padding: 18px;
        }

        .signal h2 {
            margin: 0 0 10px;
            font-size: 1rem;
            letter-spacing: -0.02em;
        }

        .signal p {
            margin: 0;
            color: #c2d1e1;
            line-height: 1.55;
            font-size: 0.94rem;
        }

        .signal + .signal {
            margin-top: 12px;
        }

        .status-foot {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            color: var(--muted);
            font-size: 0.8rem;
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
            padding: 7px 11px;
            color: #d3e1ef;
        }

        @media (max-width: 900px) {
            .grid {
                grid-template-columns: 1fr;
                min-height: auto;
            }

            .copy {
                border-right: 0;
                border-bottom: 1px solid var(--line);
            }
        }

        @media (max-width: 640px) {
            body {
                padding: 16px 12px;
            }

            .copy,
            .status-card {
                padding: 24px 20px;
            }

            .shell {
                width: 100%;
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
                        <strong class="hostname-value" data-firephage-hostname>{{ $domainLabel }}</strong>
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
<script>
    (function () {
        var value = document.querySelector('[data-firephage-hostname]');

        if (! value) {
            return;
        }

        var current = (value.textContent || '').trim().toLowerCase();

        if (current !== '' && current !== 'this website') {
            return;
        }

        if (window.location && window.location.hostname) {
            value.textContent = window.location.hostname;
        }
    }());
</script>
</html>
