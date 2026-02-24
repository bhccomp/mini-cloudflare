# MEMORY

## Project
- Name: FirePhage WAF SaaS
- Stack: Laravel 12, Filament 5, PostgreSQL, Redis, Nginx, Supervisor
- Domain: https://waf-saas.firephage.com

## Product Architecture (Pivoted)
- Proxy-based security SaaS (not customer-AWS automation).
- Flow: Customer Domain -> CloudFront (our AWS, WAF attached) -> Customer Origin.
- Customers do not provide AWS credentials.

## Panels (Separated)
- Admin panel: `/admin`
  - Internal only (`is_super_admin = true`)
  - Resources: Organizations, Users, Sites (all), Audit Logs, Plans, System Settings
  - Overrides: retry provisioning, check DNS/finalize
- User panel: `/app`
  - Customer users with org membership
  - Resources: Sites, Alert Rules, Alert Channels, Alert Events
  - Site actions: Provision, Check DNS, Under Attack toggle, Purge cache

## Current Provisioning State Machine
1. `draft` -> user clicks Provision
2. ACM cert requested (`us-east-1`), DNS validation records stored -> `pending_dns`
3. user clicks Check DNS
4. if validated: create WAF + CloudFront, store IDs, add traffic DNS target -> `active`
5. on failure -> `failed` with `last_error`

## Important Models/Tables
- `sites` key fields:
  - `display_name`, `apex_domain`, `www_enabled`, `www_domain`
  - `origin_type`, `origin_url`, `origin_host`
  - `status`, `under_attack`, `last_error`, `last_provisioned_at`
  - `acm_certificate_arn`, `cloudfront_distribution_id`, `cloudfront_domain_name`, `waf_web_acl_arn`
  - `required_dns_records` (JSON)
- `audit_logs`: `actor_id`, `organization_id`, `site_id`, `action`, `status`, `meta`

## Jobs (Active)
- `RequestAcmCertificateJob`
- `CheckAcmDnsValidationJob`
- `ProvisionCloudFrontDistributionJob`
- `ProvisionWafWebAclJob`
- `AssociateWebAclToDistributionJob`
- `CheckSiteDnsAndFinalizeProvisioningJob`
- `ToggleUnderAttackModeJob`
- `InvalidateCloudFrontCacheJob`

## AWS Service
- `App\Services\Aws\AwsEdgeService`
- Integrations:
  - ACM (request cert + DNS validation data)
  - DNS check (`dig` + fallback)
  - CloudFront distribution create
  - WAF WebACL create/update (rate-limit strict mode)
  - Invalidation
- Safety mode:
  - `AWS_EDGE_DRY_RUN=true` by default

## Guardrails
- Domain validation: `ApexDomainRule`
- SSRF protection: `SafeOriginUrlRule`
- Rate-limit sensitive site actions in app Site resource
- Idempotent job behavior + audit entries

## UX Update (Latest)
- `/app` dashboard now has header CTA: **Add Site**.
- Sites list now has:
  - primary header CTA: **Add Site**
  - secondary table CTA above rows
  - friendly empty-state copy + **Add your first protected site** CTA
- Site create flow is now a 4-step wizard:
  - Domain
  - Origin (with inline origin connectivity test)
  - SSL explanation
  - Review & Create
- Create action label changed to **Create protection layer**.
- After creation, user is redirected to site edit/status hub.

## Key Files
- Panel providers:
  - `app/Providers/Filament/AdminPanelProvider.php`
  - `app/Providers/Filament/UserPanelProvider.php`
- App dashboard:
  - `app/Filament/App/Pages/Dashboard.php`
- App site workflow:
  - `app/Filament/App/Resources/SiteResource.php`
  - `app/Filament/App/Resources/SiteResource/Pages/ListSites.php`
  - `app/Filament/App/Resources/SiteResource/Pages/CreateSite.php`
- Admin site override:
  - `app/Filament/Admin/Resources/SiteResource.php`
- AWS orchestration:
  - `app/Services/Aws/AwsEdgeService.php`

## Env Vars
- AWS:
  - `AWS_EDGE_ACCESS_KEY_ID`
  - `AWS_EDGE_SECRET_ACCESS_KEY`
  - `AWS_EDGE_REGION`
  - `AWS_EDGE_DRY_RUN`
  - `AWS_EDGE_MANAGE_DNS`
- Stripe (stubbed for now):
  - `STRIPE_SECRET`
  - `STRIPE_WEBHOOK_SECRET`
  - `STRIPE_PRICE_BASIC_MONTHLY`
  - `STRIPE_PRICE_PRO_MONTHLY`
  - `STRIPE_PRICE_BUSINESS_MONTHLY`

## Test/Health Snapshot
- `php artisan test` passes
- `php artisan optimize` passes

## Recent Commits
- `4e53395` fix(filament): v5 action namespace compatibility
- `d89c9bf` docs: proxy provisioning flow and panel responsibilities
- `f3886ce` feat(aws-admin): AWS proxy integrations + admin overrides + guardrails
- `dda0f8c` feat(app): app site workflow + provision/check-dns jobs
- `c1c6fb5` feat(core): proxy schema pivot + panel isolation verification

## Hotfixes (Latest 500s)
- 500 on `/app/sites/create` root cause #1:
  - `Class "Filament\\Forms\\Components\\Section" not found`
  - Fixed by using `Filament\\Schemas\\Components\\Section`.
  - Commit: `17acb48`
- 500 on `/app/sites/create` root cause #2:
  - `Class "Filament\\Forms\\Components\\Wizard" not found`
  - Fixed by using `Filament\\Schemas\\Components\\Wizard` and `Filament\\Schemas\\Components\\Wizard\\Step`.
  - Commit: `6fe87f3`
- Post-fix actions run:
  - `php artisan optimize`
  - `php artisan test` (all passing)
  - `systemctl reload php8.3-fpm`

## Site Edit UX (Stacked Security Control Panel)
- Site edit page (`/app/sites/{id}/edit`) now uses 5 stacked sections:
  - SSL
  - CDN
  - Cache
  - WAF
  - Origin
- Create page remains a wizard; edit page is now control-oriented instead of generic CRUD.
- Functional controls on edit:
  - CDN/Cache purge actions -> `InvalidateCloudFrontCacheJob`
  - Under attack toggle -> `ToggleUnderAttackModeJob`
- Placeholder controls (queued + audited):
  - HTTPS enforcement toggle
  - Cache enabled toggle
  - Cache mode selector
  - WAF ruleset preset selector
  - Origin protection toggle
- Placeholder control job: `ApplySiteControlSettingJob` (audit-only for now).
