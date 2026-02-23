# FirePhage WAF SaaS

Security-first multi-tenant WAF/CDN dashboard built with Laravel + Filament.

## Panels (Fully Isolated)
- `Admin Panel`: `/admin`
  - Super admins/internal operators only
  - Users, organizations, global sites, plans, audit logs, system settings
  - Monitoring and billing overview pages are currently placeholders: **Coming soon**
- `User Panel`: `/app`
  - Customer users only
  - Their own sites, alert rules/channels/events, billing and organization settings
  - Billing and analytics details currently placeholders: **Coming soon**

## MVP Domain Model
- `organizations`, `organization_user`, `sites`
- `alert_rules`, `alert_channels`, `alert_events`, `site_events`
- `audit_logs`
- `plans`, `organization_subscriptions`, `system_settings`

## Provisioning Jobs (Queue)
- `ProvisionCloudFrontJob`
- `ProvisionWafJob`
- `ToggleUnderAttackModeJob`
- `InvalidateCacheJob`

All provisioning is asynchronous and audit-logged.

## AWS/Stripe Config
Set in `.env`:
- `AWS_EDGE_ACCESS_KEY_ID`
- `AWS_EDGE_SECRET_ACCESS_KEY`
- `AWS_EDGE_REGION`
- `AWS_EDGE_DRY_RUN=true|false`
- `STRIPE_SECRET`
- `STRIPE_WEBHOOK_SECRET`
- `STRIPE_PRICE_BASIC_MONTHLY`
- `STRIPE_PRICE_PRO_MONTHLY`
- `STRIPE_PRICE_BUSINESS_MONTHLY`

`AWS_EDGE_DRY_RUN=true` is the default-safe mode for MVP scaffolding.

## Local Dev
1. `composer install`
2. `cp .env.example .env && php artisan key:generate`
3. Configure DB/Redis in `.env`
4. `php artisan migrate`
5. `pnpm install && pnpm build`
6. `php artisan serve`
7. `php artisan queue:work`

## Production Ops
1. Pull and install:
   - `git pull`
   - `composer install --no-dev --optimize-autoloader`
   - `pnpm install && pnpm build`
2. Run schema/app caches:
   - `php artisan migrate --force`
   - `php artisan optimize`
3. Restart workers/services:
   - `supervisorctl restart firephage-queue:*`
   - `systemctl reload nginx`

## Security Notes
- Admin and user data access are separated by panel and scoped queries/policies.
- Sensitive site actions are rate-limited in UI actions.
- Origin URL validation blocks local/private targets.
- No secrets are stored in git.
