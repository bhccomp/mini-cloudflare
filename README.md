# FirePhage WAF SaaS

Proxy-based WAF/CDN security SaaS (not customer-AWS automation).

Traffic model:
- Customer domain -> CloudFront (our AWS account, WAF attached) -> Customer origin

## Panels
- Admin panel: `/admin`
  - Internal operators only (`is_super_admin = true`)
  - Organizations, users, all sites, audit logs, plans, system settings
  - Retry/override provisioning actions
- User panel: `/app`
  - Customer users with organization membership
  - Sites provisioning flow, alert resources, billing/settings placeholders

## MVP Provisioning Flow
1. Create site (`display_name`, domain, origin)
2. Click `Provision`
   - ACM cert requested in `us-east-1`
   - ACM DNS validation records stored in `required_dns_records`
   - status -> `pending_dns`
3. Click `Check DNS`
   - server-side DNS check (`dig` + fallback lookup)
   - on success: create WAF + CloudFront, persist IDs
   - add traffic DNS target records (domain -> `*.cloudfront.net`)
   - status -> `active`

Actions:
- Under Attack Mode (WAF stricter rate limit)
- Purge Cache (CloudFront invalidation)

## Guardrails
- Domain validation (`ApexDomainRule`)
- SSRF protection for origin URL (`SafeOriginUrlRule`)
- Rate-limited sensitive site actions
- Idempotent queue jobs with audit entries

## Main Jobs
- `StartSiteProvisioningJob`
- `CheckSiteDnsAndFinalizeProvisioningJob`
- `ToggleUnderAttackModeJob`
- `InvalidateCacheJob`

## AWS Integration Service
- `App\Services\Aws\AwsEdgeService`
- Uses AWS SDK clients for:
  - ACM
  - CloudFront
  - WAFV2
- Safe mode default: `AWS_EDGE_DRY_RUN=true`

## Required Env
- `AWS_EDGE_ACCESS_KEY_ID`
- `AWS_EDGE_SECRET_ACCESS_KEY`
- `AWS_EDGE_REGION=us-east-1`
- `AWS_EDGE_DRY_RUN=true|false`
- `AWS_EDGE_MANAGE_DNS=false` (MVP default)
- Stripe keys (for billing work):
  - `STRIPE_SECRET`
  - `STRIPE_WEBHOOK_SECRET`
  - `STRIPE_PRICE_BASIC_MONTHLY`
  - `STRIPE_PRICE_PRO_MONTHLY`
  - `STRIPE_PRICE_BUSINESS_MONTHLY`

## Local / CI checks
- `php artisan migrate --force`
- `php artisan test`
- `php artisan optimize`

## Production deployment
1. `git pull`
2. `composer install --no-dev --optimize-autoloader`
3. `pnpm install && pnpm build`
4. `php artisan migrate --force`
5. `php artisan optimize`
6. `supervisorctl restart firephage-queue:*`
7. `systemctl reload nginx`

## Notes
- Keep secrets in `.env` only, never in git.
- `required_dns_records` stores customer-facing DNS steps for ACM validation and traffic cutover.
