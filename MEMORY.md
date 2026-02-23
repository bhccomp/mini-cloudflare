# MEMORY

## Project
- Name: FirePhage WAF SaaS
- Stack: Laravel 12, Filament 5, PostgreSQL, Redis, Nginx, Supervisor
- Server: Hetzner Ubuntu (production)
- Domain: https://waf-saas.firephage.com

## Panel Architecture (Separated)
- Admin panel: `/admin`
  - Super admins/internal operators only
  - Resources: Users, Organizations, All Sites, Plans, Audit Logs, System Settings
  - Pages: Billing Overview (Coming soon), Global Monitoring (Coming soon)
- User panel: `/app`
  - Customer users only
  - Resources: Sites, Alert Rules, Alert Channels, Alert Events
  - Pages: Billing (Coming soon), Organization Settings (Coming soon)

## Access Rules
- `User::canAccessPanel()` controls panel access:
  - `is_super_admin=true` => `/admin`
  - non-super users with organization membership => `/app`
- Policies:
  - `SitePolicy` and `OrganizationPolicy` enforce tenant scope

## Data Model (MVP)
- Tenancy: `organizations`, `organization_user`, `sites`
- Security/alerts: `alert_rules`, `alert_channels`, `alert_events`, `site_events`
- Ops: `audit_logs`
- Billing/settings: `plans`, `organization_subscriptions`, `system_settings`
- Users include: `is_super_admin`, `current_organization_id`

## Provisioning Jobs (Queue)
- `ProvisionCloudFrontJob`
- `ProvisionWafJob`
- `ToggleUnderAttackModeJob`
- `InvalidateCacheJob`
- AWS service wrapper: `App\Services\Aws\AwsEdgeService`
- Current mode: supports dry-run (`AWS_EDGE_DRY_RUN=true`) and audit logging; full production payload templates are staged as "coming soon".

## Security Guardrails Implemented
- Apex domain validation: `ApexDomainRule`
- Origin URL SSRF guard: `SafeOriginUrlRule`
- Sensitive UI actions rate-limited in `App\Filament\App\Resources\SiteResource`

## Key File Map
- Panel providers:
  - `app/Providers/Filament/AdminPanelProvider.php`
  - `app/Providers/Filament/UserPanelProvider.php`
- Access/policies:
  - `app/Models/User.php`
  - `app/Providers/AuthServiceProvider.php`
  - `app/Policies/SitePolicy.php`
  - `app/Policies/OrganizationPolicy.php`
- User actions:
  - `app/Filament/App/Resources/SiteResource.php`
- Admin resources:
  - `app/Filament/Admin/Resources/*`
- Config:
  - `config/services.php` (AWS edge + Stripe keys)

## Env Variables to Know
- AWS edge:
  - `AWS_EDGE_ACCESS_KEY_ID`
  - `AWS_EDGE_SECRET_ACCESS_KEY`
  - `AWS_EDGE_REGION`
  - `AWS_EDGE_DRY_RUN`
- Stripe:
  - `STRIPE_SECRET`
  - `STRIPE_WEBHOOK_SECRET`
  - `STRIPE_PRICE_BASIC_MONTHLY`
  - `STRIPE_PRICE_PRO_MONTHLY`
  - `STRIPE_PRICE_BUSINESS_MONTHLY`

## Test/Validation State (last run)
- `php artisan migrate --force` passed
- `php artisan test` passed (Feature + Unit)
- `php artisan optimize` passed
- `/admin/login` and `/app/login` both return `200`

## Git State
- Commits created:
  - `05ece0f` core model/panel/auth foundation
  - `37a3256` panel resources + queued actions
  - `9e088ee` tests + README
  - `c5e5d11` scaffold housekeeping

## Ops Notes
- Queue worker managed by Supervisor:
  - config: `/etc/supervisor/conf.d/firephage-queue.conf`
- Scheduler via systemd timer:
  - `firephage-scheduler.timer`
- Nginx site:
  - `/etc/nginx/sites-available/firephage-waf-saas`

## Credentials Handling
- Do not store secrets in git.
- Temporary generated credentials are stored in root-only files on server.

## Recommended Next Steps
1. Add real AWS CloudFront + WAF request payload templates and status polling.
2. Add Stripe webhook handlers and subscription state sync.
3. Add dedicated dashboard widgets (global + tenant) using real metrics.
4. Add feature gating by plan limits in policies/services.
5. Add end-to-end tests for panel isolation and job action flows.
