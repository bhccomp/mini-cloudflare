# FirePhage WAF SaaS

Proxy-based WAF/CDN security SaaS (not customer-AWS automation).

Traffic model:
- Customer domain -> Edge provider (AWS CloudFront + WAF or Bunny Pull Zone) -> Customer origin

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
   - Provider request starts (`acm.request` action)
   - Provider validation DNS records stored in `required_dns_records` when needed
   - status -> `pending_dns_validation`
3. Click `Check DNS`
   - validation check (`acm.check_dns` action)
   - on success: edge deployment starts and persists provider IDs
   - add traffic DNS target records for cutover
   - status -> `ready_for_cutover`
4. Click `Check cutover`
   - verifies traffic DNS points to provider edge target
   - status -> `active`

Actions:
- Under Attack Mode (`waf.under_attack`, provider support varies)
- Purge Cache (`cloudfront.invalidate`, routed by provider)

## Multi-Provider Edge Layer
- Abstraction: `App\Services\Edge\EdgeProviderInterface`
- Resolver: `App\Services\Edge\EdgeProviderManager`
- Providers:
  - `App\Services\Edge\Providers\AwsCdnProvider`
  - `App\Services\Edge\Providers\BunnyCdnProvider`

Site-level provider fields:
- `sites.provider` (`aws` or `bunny`)
- `sites.provider_resource_id`
- `sites.provider_meta` (json)

Default provider:
- `EDGE_PROVIDER=aws` (fallback for new/existing sites without explicit provider)

Bunny credentials:
- Store in `system_settings` with key `bunny`
- JSON value format:
  - `{"api_key":"<BUNNY_API_KEY>"}`

## Guardrails
- Domain validation (`ApexDomainRule`)
- SSRF protection for origin URL (`SafeOriginUrlRule`)
- Rate-limited sensitive site actions
- Idempotent queue jobs with audit entries

## AWS Integration Service
- `App\Services\Aws\AwsEdgeService`
- Wrapped by `AwsCdnProvider`
- Safe mode default: `AWS_EDGE_DRY_RUN=true`

## Required Env
- `EDGE_PROVIDER=aws|bunny`
- `BUNNY_API_BASE_URL=https://api.bunny.net`
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
- `required_dns_records` stores customer-facing DNS steps for validation and traffic cutover.
