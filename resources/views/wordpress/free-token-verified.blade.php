<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>FirePhage Email Verification</title>
    <style>
        body { margin: 0; font-family: Arial, Helvetica, sans-serif; background: #070b16; color: #e2e8f0; }
        .shell { min-height: 100vh; display: grid; place-items: center; padding: 24px; }
        .card { width: min(100%, 680px); padding: 32px; border-radius: 24px; background: linear-gradient(135deg, #08101d 0%, #0d1728 50%, #111b30 100%); border: 1px solid rgba(34, 211, 238, 0.18); box-shadow: 0 24px 80px rgba(8, 15, 30, 0.45); }
        .eyebrow { margin: 0 0 14px; font-size: 12px; font-weight: 700; letter-spacing: 0.18em; text-transform: uppercase; color: #67e8f9; }
        h1 { margin: 0 0 14px; font-size: 36px; line-height: 1.1; color: #f8fafc; }
        p { margin: 0 0 14px; font-size: 16px; line-height: 1.7; color: #cbd5e1; }
        a { display: inline-block; margin-top: 8px; padding: 14px 20px; border-radius: 14px; background: #22d3ee; color: #082f49; text-decoration: none; font-weight: 700; }
    </style>
</head>
<body>
    <div class="shell">
        <div class="card">
            <p class="eyebrow">WordPress Signature Access</p>
            @if ($verified)
                <h1>Email verified</h1>
                <p>FirePhage can now activate remote signature updates for <strong>{{ $siteHost }}</strong>.</p>
                <p>Return to the WordPress plugin and click <strong>Check Verification Status</strong> to finish activation.</p>
                <a href="{{ config('app.url') }}">Open FirePhage</a>
            @else
                <h1>Verification unavailable</h1>
                <p>{{ $message ?? 'This verification link is no longer valid.' }}</p>
            @endif
        </div>
    </div>
</body>
</html>
