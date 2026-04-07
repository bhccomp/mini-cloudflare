<?php

namespace App\Services\Seo;

use App\Models\BlogPost;
use App\Models\SystemSetting;
use App\Support\MarketingSeo;

class LlmsTxtService
{
    public const SETTING_KEY = 'llms';

    public const BLOG_PLACEHOLDER = '{{blog_posts}}';

    public function render(): string
    {
        $template = $this->template();
        $blogLines = implode("\n", $this->blogLines());

        if (str_contains($template, self::BLOG_PLACEHOLDER)) {
            $content = str_replace(self::BLOG_PLACEHOLDER, $blogLines, $template);
        } else {
            $content = rtrim($template) . "\n\n## Blog\n" . $blogLines;
        }

        return rtrim($content) . "\n";
    }

    public function template(): string
    {
        $value = SystemSetting::query()->where('key', self::SETTING_KEY)->value('value');
        $template = is_array($value) ? (string) ($value['template'] ?? '') : '';

        return trim($template) !== '' ? $template : $this->defaultTemplate();
    }

    public function defaultTemplate(): string
    {
        return <<<'TXT'
# FirePhage

> FirePhage is a managed edge security platform for WordPress and WooCommerce sites. It filters hostile traffic before it reaches the origin server, reducing attack surface, bot pressure, and operational noise for site owners, stores, and agencies.

FirePhage sits in front of WordPress via DNS onboarding, placing WAF rules, bot filtering, DDoS mitigation, CDN delivery, and caching at the edge — not inside WordPress itself. The platform is designed for teams that need protection to be understandable and manageable, not just technically present. The dashboard is built around human-readable visibility rather than raw log output.

## Core Services
- [WAF](https://firephage.com/services/waf) — Managed web application firewall for WordPress, WooCommerce, Laravel, APIs, and agency portfolios.
- [Bot Protection](https://firephage.com/services/bot-protection) — Blocks login abuse, brute-force attempts, scraping, and noisy automation before it reaches origin.
- [DDoS Protection](https://firephage.com/services/ddos-protection) — Edge-first traffic pressure handling with escalation via Under Attack Mode.
- [CDN](https://firephage.com/services/cdn) — Global edge delivery with operational clarity for speed and origin offload.
- [Cache](https://firephage.com/services/cache) — Practical cache visibility and edge controls for production WordPress sites.
- [WordPress Plugin](https://firephage.com/services/wordpress-plugin) — WordPress health, malware scanning, and paid dashboard telemetry in one workflow.
- [Uptime Monitor](https://firephage.com/services/uptime-monitor) — Continuous availability checks from multiple locations with instant alerts via Slack, email, SMS, or webhooks.

## How It Works
- Protection is placed at the DNS/edge layer, not forced to live only inside WordPress.
- Onboarding involves a guided DNS cutover with staged changes and a zero-downtime target.
- The edge inspects and filters requests before they reach the origin server.
- Clean traffic is forwarded to origin; hostile traffic is blocked or challenged.
- Under Attack Mode allows operators to escalate protection posture instantly during traffic spikes.
- The dashboard connects edge security data with WordPress plugin health in one place.

## WooCommerce Protection
FirePhage includes specific workflows for WooCommerce stores facing fake orders, login abuse, checkout friction, and product scraping. Store-specific presets (such as High Bot Pressure mode) allow operators to tighten protection quickly when store traffic turns hostile. Protected flows include checkout, cart, account login, and store APIs.

## Pricing
- **Starter — $29/month** — 1 site, up to 10M requests/month. Includes WAF, DDoS, CDN, caching, bot protection, WordPress plugin, uptime monitoring, attack analytics, Slack and email alerts. 30-day free trial available.
- **Growth — $79/month** — Up to 3 sites, up to 24M requests/month. Adds performance insights.
- **Pro — $149/month** — Up to 7 sites, up to 56M requests/month. Adds priority support.
- **Enterprise — Custom pricing** — Tailored for high-traffic sites and agencies with custom requirements.

## Key Metrics
- 99.9% malicious traffic filtered before reaching origin
- <50ms edge latency for request inspection
- 3.2M+ requests filtered per month
- Zero-downtime DNS cutover target

## Blog
{{blog_posts}}

## Resources
- [Start 30-day free trial](https://firephage.com/register)
- [View live demo](https://demo.firephage.com/app)
- [Contact sales](https://firephage.com/contact)
- [Blog](https://firephage.com/blog)

## Operated By
FirePhage is operated by Dialbotics LLC. Founded by Nikola Jocic.
TXT;
    }

    public function saveTemplate(string $template): void
    {
        $setting = SystemSetting::query()->firstOrCreate(
            ['key' => self::SETTING_KEY],
            [
                'value' => [],
                'is_encrypted' => false,
                'description' => 'Editable llms.txt content template',
            ]
        );

        $setting->forceFill([
            'value' => ['template' => $template],
            'is_encrypted' => false,
            'description' => 'Editable llms.txt content template',
        ])->save();
    }

    /**
     * @return list<string>
     */
    protected function blogLines(): array
    {
        return BlogPost::query()
            ->published()
            ->orderByDesc('published_at')
            ->get()
            ->map(function (BlogPost $post): string {
                $url = MarketingSeo::preferredUrl($post->canonical_url ?: route('blog.show', $post));

                return '- [' . trim((string) $post->title) . "]({$url})";
            })
            ->values()
            ->all();
    }
}
