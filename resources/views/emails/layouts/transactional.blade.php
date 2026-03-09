<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $subject ?? 'FirePhage Security' }}</title>
    @if (! empty($preheader ?? null))
        <meta name="description" content="{{ $preheader }}">
    @endif
</head>
<body style="margin:0; padding:0; background:#070b16; font-family:Arial, Helvetica, sans-serif; color:#e2e8f0;">
    @php
        $subject = $subject ?? 'FirePhage Security';
        $preheader = $preheader ?? null;
        $eyebrow = $eyebrow ?? 'FirePhage Security';
        $headline = $headline ?? null;
        $intro = $intro ?? null;
        $ctaLabel = $ctaLabel ?? null;
        $ctaUrl = $ctaUrl ?? null;
        $metaRows = $metaRows ?? [];
        $sections = $sections ?? [];
        $footerTitle = $footerTitle ?? 'Advanced Firewall Protection For WordPress';
        $footerBody = $footerBody ?? 'Protect your site with FirePhage WAF, CDN, and cache controls from one clear dashboard.';
        $footerCtaLabel = $footerCtaLabel ?? 'Open FirePhage';
        $footerCtaUrl = $footerCtaUrl ?? config('app.url');
        $subcopy = $subcopy ?? null;
    @endphp

    @if ($preheader)
        <div style="display:none; max-height:0; overflow:hidden; opacity:0; mso-hide:all;">
            {{ $preheader }}
        </div>
    @endif

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#070b16; margin:0; padding:24px 0; width:100%;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="width:100%; max-width:640px; margin:0 auto;">
                    <tr>
                        <td style="padding:0 16px 16px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="width:100%; border-radius:28px; overflow:hidden; background:linear-gradient(135deg, #08101d 0%, #0d1728 48%, #101c31 100%); border:1px solid rgba(34, 211, 238, 0.18); box-shadow:0 24px 80px rgba(8, 15, 30, 0.45);">
                                <tr>
                                    <td style="padding:32px 32px 18px; background:radial-gradient(circle at top right, rgba(34, 211, 238, 0.24), transparent 34%), radial-gradient(circle at top left, rgba(56, 189, 248, 0.18), transparent 30%);">
                                        <div style="font-size:12px; letter-spacing:0.18em; text-transform:uppercase; color:#67e8f9; font-weight:700; margin-bottom:14px;">
                                            {{ $eyebrow }}
                                        </div>
                                        <div style="font-size:34px; line-height:1.15; font-weight:700; color:#f8fafc; margin:0 0 14px;">
                                            {{ $headline }}
                                        </div>
                                        @if ($intro)
                                            <div style="font-size:16px; line-height:1.7; color:#cbd5e1; margin:0;">
                                                {{ $intro }}
                                            </div>
                                        @endif
                                    </td>
                                </tr>

                                @if ($ctaLabel && $ctaUrl)
                                    <tr>
                                        <td style="padding:0 32px 26px;">
                                            <a href="{{ $ctaUrl }}" style="display:inline-block; background:#22d3ee; color:#082f49; text-decoration:none; font-size:14px; line-height:14px; font-weight:700; padding:15px 22px; border-radius:14px;">
                                                {{ $ctaLabel }}
                                            </a>
                                        </td>
                                    </tr>
                                @endif

                                @if (! empty($metaRows))
                                    <tr>
                                        <td style="padding:0 32px 26px;">
                                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="width:100%; border-collapse:separate; border-spacing:0 10px;">
                                                @foreach ($metaRows as $row)
                                                    <tr>
                                                        <td style="width:38%; padding:14px 16px; border-radius:14px 0 0 14px; background:rgba(15, 23, 42, 0.86); color:#7dd3fc; font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:0.08em;">
                                                            {{ $row['label'] }}
                                                        </td>
                                                        <td style="padding:14px 16px; border-radius:0 14px 14px 0; background:rgba(15, 23, 42, 0.86); color:#e2e8f0; font-size:14px; line-height:1.6;">
                                                            {{ $row['value'] }}
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </table>
                                        </td>
                                    </tr>
                                @endif

                                @if (! empty($sections))
                                    <tr>
                                        <td style="padding:0 32px 18px;">
                                            @foreach ($sections as $section)
                                                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="width:100%; margin:0 0 14px; border:1px solid rgba(148, 163, 184, 0.14); border-radius:18px; background:rgba(15, 23, 42, 0.72);">
                                                    <tr>
                                                        <td style="padding:18px 20px;">
                                                            @if (! empty($section['title']))
                                                                <div style="font-size:15px; line-height:1.4; font-weight:700; color:#f8fafc; margin:0 0 8px;">
                                                                    {{ $section['title'] }}
                                                                </div>
                                                            @endif
                                                            @if (! empty($section['body']))
                                                                <div style="font-size:14px; line-height:1.7; color:#cbd5e1; margin:0;">
                                                                    {!! nl2br(e($section['body'])) !!}
                                                                </div>
                                                            @endif
                                                        </td>
                                                    </tr>
                                                </table>
                                            @endforeach
                                        </td>
                                    </tr>
                                @endif

                                <tr>
                                    <td style="padding:10px 32px 32px;">
                                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="width:100%; border-radius:20px; background:linear-gradient(135deg, rgba(8, 47, 73, 0.9), rgba(21, 94, 117, 0.88));">
                                            <tr>
                                                <td style="padding:20px 22px;">
                                                    <div style="font-size:15px; line-height:1.4; font-weight:700; color:#f8fafc; margin:0 0 8px;">
                                                        {{ $footerTitle }}
                                                    </div>
                                                    <div style="font-size:14px; line-height:1.7; color:#dbeafe; margin:0 0 14px;">
                                                        {{ $footerBody }}
                                                    </div>
                                                    @if ($footerCtaLabel && $footerCtaUrl)
                                                        <a href="{{ $footerCtaUrl }}" style="display:inline-block; color:#cffafe; text-decoration:none; font-size:13px; font-weight:700;">
                                                            {{ $footerCtaLabel }} →
                                                        </a>
                                                    @endif
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:0 24px; text-align:center; color:#64748b; font-size:12px; line-height:1.7;">
                            <div>{{ $subject }}</div>
                            <div style="margin-top:6px;">FirePhage Security · {{ parse_url(config('app.url'), PHP_URL_HOST) ?: 'firephage.com' }}</div>
                            @if ($subcopy)
                                <div style="margin-top:10px;">{{ $subcopy }}</div>
                            @endif
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
