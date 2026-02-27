# MEMORY

## Organization Roles + Invitations (Latest)
- Added organization-level RBAC mapping with roles and permission keys:
  - Roles: `owner`, `admin`, `editor`, `viewer`
  - Permissions: `sites_read`, `sites_write`, `alerts_read`, `alerts_write`, `logs_read`, `members_manage`, `settings_manage`, `billing_read`
- Added `OrganizationAccessService` for centralized permission resolution and checks across current organization context.
- Added per-membership custom permissions on `organization_user.permissions` (JSON).
- Added organization invitations flow:
  - New table `organization_invitations` with token, role, custom permissions, expiry, accepted/revoked timestamps.
  - New model `OrganizationInvitation`.
  - New mail notification `OrganizationInvitationNotification`.
  - New authenticated accept endpoint:
    - `GET /app/invitations/{token}/accept` (`app.invitations.accept`)
    - validates pending token + email match, attaches membership, marks invite accepted.
- Upgraded `/app/organization-settings` page:
  - Team invite form (email + read/write/admin + optional advanced permissions)
  - Members management (change role/remove)
  - Pending invitations list (revoke)
  - Read-only users see non-editable state.
- Policy updates:
  - `SitePolicy` now explicitly uses permission checks (`sites_read` / `sites_write`).
  - `OrganizationPolicy` update/member-management uses `settings_manage` / `members_manage`.
- Added tests:
  - `tests/Feature/OrganizationInvitationTest.php`
  - Extended `tests/Feature/SitePolicyTest.php` for viewer read-only behavior.
- Validation status:
  - `./vendor/bin/pint --dirty` passed
  - `php artisan test` passed
  - `php artisan optimize` passed

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

## Global Site Context (Topbar + Dashboard)
- Added global topbar Site Switcher via `PanelsRenderHook::TOPBAR_START`.
- Site switcher lists all user-owned sites with status badge, stores selection in session key `selected_site_id`, and includes `Add site` quick link.
- New Site Dashboard at `/app` now loads selected site from session and shows:
  - Metrics row: blocked requests (24h), cache hit ratio, certificate status, distribution health
  - 5 stacked control cards: SSL, CDN, Cache, WAF, Origin
  - Quick actions: SSL request, purge cache, under attack toggle, placeholder control toggles
- Edit Site page now enforces selected-site context from session and redirects to Sites list if missing/invalid selection.

## Cloudflare-like /app UX Refactor
- Added global Protection navigation pages (non-CRUD):
  - `/app/overview`
  - `/app/ssl`
  - `/app/cdn`
  - `/app/cache`
  - `/app/firewall`
  - `/app/origin`
  - `/app/analytics` (placeholder)
  - `/app/logs` (placeholder)
- Added `SiteContext` service for per-user selected site handling:
  - supports URL override `?site_id=...`
  - supports `All sites` mode (`null`)
  - persists selection in session and `users.selected_site_id`
  - enforces org ownership for selected site
- Added global topbar switcher (render hook `PanelsRenderHook::TOPBAR_START`) with:
  - current site / All sites label
  - all owned sites (`display_name + apex_domain + status`)
  - Add site action
- Added migration: `users.selected_site_id` (nullable FK to sites).
- Create Site wizard now redirects to overview and auto-selects the new site context.
- New tests in `tests/Feature/AppProtectionNavigationTest.php` cover:
  - no-site access to protection pages
  - selected-site visibility across pages
  - create wizard redirect + selected-site persistence

## UI Refinements (Latest)
- Create Site wizard UX simplified:
  - Removed separate `site name` field from UI.
  - Single required `Domain` input now accepts apex domain or URL.
  - Domain input is normalized (scheme/path/leading `www` stripped) before save.
  - `display_name` and `name` are auto-generated internally from normalized apex domain.
- Topbar Site Switcher redesigned:
  - Removed duplicated domain labeling.
  - Now shows apex domain + compact status badge (`Active`/`Draft`).
  - Added quick actions at top: `All sites`, `+ Add site`.
  - Added live search (`wire:model.live.debounce`) for large site lists.
  - Improved spacing, separators, selected-state visibility, and dropdown width.
- Protection pages visual polish:
  - Added consistent `max-w-7xl` card-based layout shell.
  - Unified site context header card and empty state card styling.
  - Improved spacing/alignment for Overview and section pages (SSL/CDN/Cache/Firewall/Origin/Analytics/Logs).
- Redirect behavior remains:
  - After site create, redirect to `/app/overview?site_id=...` with context selected.

## UI Hotfix + Visual Refresh (Latest)
- Fixed site switcher dropdown clipping/expanding topbar issue by replacing custom absolute popup with Filament native `x-filament::dropdown` (`teleport=true`).
- Switcher keeps search + quick actions + selected-state badges, now rendered in stable overlay behavior.
- Applied additional Cloudflare-like visual polish:
  - Overview: gradient hero, cleaner stat cards, tighter control-card rhythm.
  - Section pages (SSL/CDN/Cache/Firewall/Origin/Analytics/Logs): consistent hero headers, spacing, and modern card-based layout.
- Create Site UX remains simplified (single domain input, normalized, auto display name).

## UI Adjustment (Latest)
- Removed extra page-level title/domain layer outside widgets across protection pages.
- `BaseProtectionPage::getHeading()` now returns `null`, so top page heading is hidden.
- Removed middle hero/domain blocks from Overview and all protection pages.
- Kept only widget/section cards visible on each page.
- Site switcher remains as Filament teleported dropdown (overlay behavior fixed).

## Dashboard Widget Upgrade (Latest)
- `/app/overview` now uses native Filament widgets (not plain inline stat blocks):
  - `SiteSignalsStats` (stats overview)
  - `TrafficTrendChart` (line chart)
  - `CacheDistributionChart` (doughnut chart)
  - `TrafficRegionsWidget` (map-style regional distribution card)
- `Dashboard.php` now registers `getHeaderWidgets()` + responsive widget columns.
- Added widget concern:
  - `app/Filament/App/Widgets/Concerns/ResolvesSelectedSite.php`
  - Ensures widgets render only when a valid selected site exists.
- Added widget view:
  - `resources/views/filament/app/widgets/traffic-regions-widget.blade.php`
- Section pages (SSL/CDN/Cache/Firewall/Origin/Analytics/Logs) were refactored to richer card-based layouts with better spacing and visual hierarchy.
- Validation snapshot after update:
  - `./vendor/bin/pint` passed
  - `php artisan test` passed (9 tests)
  - `php artisan optimize` passed

## Widget Polish Follow-up (Latest)
- Removed custom `TrafficRegionsWidget` HTML-heavy block and replaced it with native Filament charts:
  - `RegionalTrafficShareChart` (bar)
  - `RegionalThreatLevelChart` (radar)
- Dashboard widget stack now uses only native-style stats/charts for region insights.
- Deleted old files:
  - `app/Filament/App/Widgets/TrafficRegionsWidget.php`
  - `resources/views/filament/app/widgets/traffic-regions-widget.blade.php`
- Validation rerun:
  - `./vendor/bin/pint` passed
  - `php artisan test` passed
  - `php artisan optimize` passed

## Settings Layout System Refactor (Latest)
- Added reusable anonymous Blade components for app settings UI:
  - `resources/views/components/filament/app/settings/card.blade.php`
  - `resources/views/components/filament/app/settings/section.blade.php`
  - `resources/views/components/filament/app/settings/key-value-grid.blade.php`
  - `resources/views/components/filament/app/settings/action-row.blade.php`
  - `resources/views/components/filament/app/settings/status-pill.blade.php`
- Refactored page layouts to use this system:
  - `resources/views/filament/app/pages/dashboard.blade.php`
  - `resources/views/filament/app/pages/protection/ssl.blade.php`
  - `resources/views/filament/app/pages/protection/cdn.blade.php`
  - `resources/views/filament/app/pages/protection/cache.blade.php`
  - `resources/views/filament/app/pages/protection/firewall.blade.php`
  - `resources/views/filament/app/pages/protection/origin.blade.php`
  - `resources/views/filament/app/pages/protection/analytics.blade.php`
  - `resources/views/filament/app/pages/protection/logs.blade.php`
- `Protection Control Stack` is now a grouped multi-section card (SSL/TLS, CDN/Cache, Firewall, Origin) with aligned key-value grids and action rows.
- Visual consistency updates:
  - fixed card max width (`max-w-6xl`) to avoid stretched content
  - consistent section dividers, spacing, status pills, and action alignment
  - mobile-first 1-column behavior with desktop 2-column key/value structure
- Validation snapshot:
  - `./vendor/bin/pint` passed
  - `php artisan test` passed
  - `php artisan optimize` passed

## Settings Widget Styling Correction (Latest)
- Adjusted shared settings UI components to restore native Filament widget feel (instead of flat/plain blocks):
  - `resources/views/components/filament/app/settings/card.blade.php`
  - `resources/views/components/filament/app/settings/section.blade.php`
  - `resources/views/components/filament/app/settings/key-value-grid.blade.php`
  - `resources/views/components/filament/app/settings/action-row.blade.php`
- `SettingsCard` now renders as `x-filament::section` with native widget chrome.
- `SettingsSection` now renders as a structured inner panel with clear header/body/action alignment.
- `KeyValueGrid` now uses denser mini-cards for label/value rows (2-col desktop, 1-col mobile).
- Validation rerun after correction:
  - `./vendor/bin/pint` passed
  - `php artisan test` passed
  - `php artisan optimize` passed

## Native Filament Layout Hardening (Latest)
- Root cause of plain-looking UI confirmed:
  - Protection page structure depended too heavily on utility-class styling, which degraded when theme CSS utility generation did not fully cover those classes.
- Shared settings components were hardened to rely on Filament-native primitives:
  - `resources/views/components/filament/app/settings/card.blade.php`
  - `resources/views/components/filament/app/settings/section.blade.php`
  - `resources/views/components/filament/app/settings/key-value-grid.blade.php`
  - `resources/views/components/filament/app/settings/action-row.blade.php`
  - `resources/views/components/filament/app/settings/status-pill.blade.php`
- Added a stable non-utility layout scaffold for protection/overview pages:
  - `resources/views/components/filament/app/settings/layout-styles.blade.php`
  - Provides consistent shell/grid spacing and responsive 2-col -> 1-col fallback independent of Tailwind utility compilation.
- Applied stabilized layout to all protection pages:
  - `resources/views/filament/app/pages/protection/ssl.blade.php`
  - `resources/views/filament/app/pages/protection/cdn.blade.php`
  - `resources/views/filament/app/pages/protection/cache.blade.php`
  - `resources/views/filament/app/pages/protection/firewall.blade.php`
  - `resources/views/filament/app/pages/protection/origin.blade.php`
  - `resources/views/filament/app/pages/protection/analytics.blade.php`
  - `resources/views/filament/app/pages/protection/logs.blade.php`
- Overview stabilization completed:
  - `resources/views/filament/app/pages/dashboard.blade.php` now uses the same stable layout include.
  - `Security Signals` graph widgets were intentionally left unchanged (`SiteSignalsStats`, `TrafficTrendChart`, `CacheDistributionChart`, `RegionalTrafficShareChart`, `RegionalThreatLevelChart`).
- Validation after hardening:
  - `./vendor/bin/pint --dirty` passed
  - `php artisan test` passed (9 tests)
  - `php artisan optimize` passed

## Option A Onboarding Flow + Status Hub (Latest)
- Provisioning flow switched to **Option A** (no traffic cutover before cert + edge):
  - `draft`
  - `pending_dns_validation`
  - `deploying`
  - `ready_for_cutover`
  - `active`
  - `failed`
- Added explicit status constants + labels:
  - `app/Models/Site.php`
- Added migration to map legacy statuses:
  - `database/migrations/2026_02_24_160000_update_site_statuses_option_a.php`
- Provision action behavior updated:
  - `RequestAcmCertificateJob` now only requests ACM + stores validation records + sets `pending_dns_validation`.
- DNS validation check behavior updated:
  - `CheckAcmDnsValidationJob` is status-aware.
  - On validation success: sets `deploying` and dispatches chain:
    - `ProvisionWafWebAclJob`
    - `ProvisionCloudFrontDistributionJob`
    - `AssociateWebAclToDistributionJob`
    - `MarkSiteReadyForCutoverJob` (new)
  - On validation pending: remains `pending_dns_validation` with friendly audit/log message.
- Cutover check behavior updated:
  - `CheckAcmDnsValidationJob` handles `ready_for_cutover` by running traffic DNS checks and sets `active` only when apex/www point to CloudFront.
- New job:
  - `app/Jobs/MarkSiteReadyForCutoverJob.php`
- New Setup Hub page:
  - `app/Filament/App/Pages/SiteStatusHubPage.php`
  - `resources/views/filament/app/pages/site-status-hub.blade.php`
  - Shows 4-step progression, contextual next action, validation DNS records, cutover DNS instructions.
  - Includes live polling refresh and in-action loading indicator UX.
- Wizard UX changes:
  - Removed “Also protect www” question from main flow; default protection includes apex + www.
  - Origin input accepts host-only and normalizes to `https://...`.
  - Redirect after create now goes to Status Hub and selects site context.
  - Files:
    - `app/Filament/App/Resources/SiteResource.php`
    - `app/Filament/App/Resources/SiteResource/Pages/CreateSite.php`
- Sites list actions/status behavior:
  - Primary action is Status Hub.
  - Provision / Check DNS / Check cutover / Under Attack / Purge actions now status/resource-aware.
  - Table row click now routes to Status Hub (not edit page).
- Site switcher routing fix:
  - Fixed Livewire redirect bug causing `405 Method Not Allowed` by persisting return URL in component state.
  - File: `app/Livewire/Filament/App/SiteSwitcher.php`
- Empty state UX for “All sites”:
  - Now shows compact site list with inline status badges and direct domain links instead of blank state.
  - File: `resources/views/filament/app/pages/protection/empty-state.blade.php`

## Provider-Managed Analytics Pipeline (Latest)
- Added backend metrics storage for dashboard analytics:
  - Model: `app/Models/SiteAnalyticsMetric.php`
  - Relation: `Site::analyticsMetric()`
  - Migration: `database/migrations/2026_02_24_210000_create_site_analytics_metrics_table.php`
- Added AWS analytics fetch service:
  - `app/Services/Aws/AwsAnalyticsService.php`
  - Pulls CloudWatch metrics for CloudFront/WAF using provider account credentials:
    - Requests
    - CacheHitRate
    - BlockedRequests
    - AllowedRequests
  - Produces persisted 24h metrics + 7-day trend arrays + regional datasets (derived where native region splits are unavailable).
  - Supports dry-run mode for safe local/dev behavior.
- Added analytics sync job + scheduler:
  - Job: `app/Jobs/SyncSiteAnalyticsMetricJob.php`
  - Console command: `metrics:sync-sites`
  - Schedule: every 5 minutes in `routes/console.php`
- Dashboard widgets now consume persisted analytics metrics (instead of static chart arrays):
  - `app/Filament/App/Widgets/SiteSignalsStats.php`
  - `app/Filament/App/Widgets/TrafficTrendChart.php`
  - `app/Filament/App/Widgets/CacheDistributionChart.php`
  - `app/Filament/App/Widgets/RegionalTrafficShareChart.php`
  - `app/Filament/App/Widgets/RegionalThreatLevelChart.php`
- Protection page helper metrics updated to read analytics snapshots:
  - `app/Filament/App/Pages/BaseProtectionPage.php`

## Tests + Validation (Latest)
- Added/updated tests:
  - `tests/Feature/AppProtectionNavigationTest.php`
  - `tests/Feature/ProvisionJobsTest.php`
  - `tests/Feature/SiteAnalyticsSyncTest.php` (new)
- Latest validation snapshot:
  - `php artisan migrate --force` passed
  - `./vendor/bin/pint --dirty` passed
  - `php artisan test` passed (14 tests, 61 assertions)
  - `php artisan optimize` passed

## Firewall Page Upgrade (Latest)
- `/app/firewall` was upgraded from basic control placeholders into a functional analytics view using Filament-native sections/badges/actions.
- Added AWS firewall insights service:
  - `app/Services/Aws/AwsFirewallInsightsService.php`
  - Pulls sampled request data from AWS WAF (`GetSampledRequests`) when not in dry-run.
  - Caches per-site insights for short intervals to keep page responsive.
  - Includes dry-run synthetic insights so UI remains populated in test/dev.
- Firewall page now shows:
  - summary metrics (sampled total/blocked/allowed/counted + block ratio)
  - top 10 countries by request count
  - top 10 IPs by request count (+ blocked count)
  - recent firewall events feed with action/rule badges
  - request map visualization (world-style map with country request bubbles)
- Added page logic:
  - `app/Filament/App/Pages/FirewallPage.php`
  - provides insights accessor, cache refresh action, and request-map point mapping.
- Updated view:
  - `resources/views/filament/app/pages/protection/firewall.blade.php`
  - Uses Filament UI primitives heavily (`x-filament::section`, badges, actions, buttons) with lightweight supplemental CSS only for layout/map rendering.

## Multi-Provider Edge Refactor (AWS + Bunny) (Latest)
- Added edge provider abstraction and resolver:
  - `app/Services/Edge/EdgeProviderInterface.php`
  - `app/Services/Edge/EdgeProviderManager.php`
- Added provider implementations:
  - `app/Services/Edge/Providers/AwsCdnProvider.php` (wraps existing `AwsEdgeService`)
  - `app/Services/Edge/Providers/BunnyCdnProvider.php` (Bunny Pull Zone create + hostname attach + DNS check + cache purge)
- Added provider persistence fields on sites:
  - migration: `database/migrations/2026_02_24_231000_add_edge_provider_columns_to_sites_table.php`
  - model updates: `provider`, `provider_resource_id`, `provider_meta`
  - default provider fallback set from config in `Site` model.
- Added provider config:
  - `config/edge.php`
  - `.env.example` keys:
    - `EDGE_PROVIDER=aws`
    - `BUNNY_API_BASE_URL=https://api.bunny.net`
- Refactored provisioning and runtime jobs to resolve provider per site (default AWS):
  - `RequestAcmCertificateJob`
  - `CheckAcmDnsValidationJob`
  - `InvalidateCloudFrontCacheJob`
  - `ToggleUnderAttackModeJob`
  - legacy jobs (`ProvisionWafWebAclJob`, `ProvisionCloudFrontDistributionJob`, `AssociateWebAclToDistributionJob`) no longer call AWS service directly
- Added unified deployment job:
  - `app/Jobs/ProvisionEdgeDeploymentJob.php`
  - `CheckAcmDnsValidationJob` now chains:
    - `ProvisionEdgeDeploymentJob`
    - `MarkSiteReadyForCutoverJob`
- Updated readiness checks:
  - `MarkSiteReadyForCutoverJob` now validates prerequisites per provider (AWS vs Bunny).
- Create-site flow now stamps default provider:
  - `app/Filament/App/Resources/SiteResource/Pages/CreateSite.php`
- App service container registers resolver singleton:
  - `app/Providers/AppServiceProvider.php`
- Docs updated:
  - `README.md` now includes multi-provider usage + Bunny system setting format
- New tests:
  - `tests/Unit/AwsCdnProviderTest.php`
  - `tests/Unit/BunnyCdnProviderTest.php`
- Updated tests:
  - `tests/Feature/ProvisionJobsTest.php` migrated to provider-manager workflow
- Validation run:
  - `php artisan migrate --force` passed
  - `php artisan test tests/Feature/ProvisionJobsTest.php tests/Unit/AwsCdnProviderTest.php tests/Unit/BunnyCdnProviderTest.php` passed (8 tests, 29 assertions)

## Git Remote Sync Process (Latest)
- Remote repository:
  - `origin = https://github.com/bhccomp/mini-cloudflare.git`
- Local repo identity:
  - `user.name = bhccomp`
  - `user.email = bhccomp@gmail.com`
- Persistent auth is configured on this machine through git credential storage for GitHub.
- Required sync routine for every work session:
  1. `git checkout master`
  2. `git pull --rebase origin master`
  3. Implement changes and commit in logical commits.
  4. `git push origin master`
- Operational rule:
  - Keep local and remote in sync continuously by pushing after each completed task and pulling before new changes.

## Bunny-First Onboarding + Cutover UX Hardening (Latest)
- Implemented Bunny-first onboarding flow while preserving AWS compatibility paths.
- Added site-level onboarding state model:
  - `onboarding_status` values include:
    - `draft`
    - `pending_dns_validation`
    - `provisioning_edge`
    - `pending_dns_cutover`
    - `dns_verified_ssl_pending`
    - `live`
    - `failed`
  - `last_checked_at` timestamp added.
- Added migration:
  - `database/migrations/2026_02_25_010000_add_onboarding_columns_to_sites_table.php`
- Provider defaults/config:
  - `DEFAULT_EDGE_PROVIDER=bunny`
  - `FEATURE_AWS_ONBOARDING=false`
  - `BUNNY_API_BASE_URL` retained
  - files: `config/edge.php`, `.env.example`
- Provider contract extended and normalized:
  - `EdgeProviderInterface`: `provision()`, `checkDns()`, `checkSsl()`, `purgeCache()`
  - AWS and Bunny providers updated accordingly.
- Create Site wizard updated:
  - Bunny-first copy and flow
  - Provider persisted per-site on create
  - Advanced provider selection available (AWS retained as advanced path)
  - files: `app/Filament/App/Resources/SiteResource.php`, `CreateSite.php`
- Status Hub updated to provider-aware flows:
  - Bunny stepper:
    1. Create site
    2. Provision edge
    3. Update DNS
    4. Verify cutover
    5. Protection active
  - AWS legacy branch kept for provider=aws
  - file: `resources/views/filament/app/pages/site-status-hub.blade.php`
- Runtime UX fixes:
  - Bunny checks now run synchronously from UI actions (`Check now`) with friendly status messages.
  - Avoided stuck state by forcing `onboarding_status=failed` on provisioning exceptions.
  - Added guarded exception handling to avoid generic “Error while loading page” crash during Bunny checks.
  - Added robust copy-to-clipboard helper with fallback for DNS target copy buttons.
- DNS check hardening for Bunny:
  - `checkDns()` now evaluates CNAME + A + AAAA lookup results.
  - Added SSL-state fallback to reduce false negatives with flattened/proxied DNS.
- Tests updated/added:
  - `tests/Feature/ProvisionJobsTest.php`
  - `tests/Unit/AwsCdnProviderTest.php`
  - `tests/Unit/BunnyCdnProviderTest.php`
  - `tests/Unit/SiteProviderSelectionTest.php` (new)
- Validation snapshot:
  - `php artisan migrate --force` passed
  - targeted provisioning/provider tests passing after refactor and UX hardening.

## Bunny Data Integration for Existing Protection Tabs (Latest)
- Goal completed: kept existing tab structure (`CDN`, `Firewall`, `Logs`, `Analytics`) and wired backend to provider-aware data paths.
- Added Bunny service layer:
  - `app/Services/Bunny/BunnyApiService.php`
  - `app/Services/Bunny/BunnyLogsService.php`
  - `app/Services/Bunny/BunnyFirewallInsightsService.php`
  - `app/Services/Bunny/BunnyAnalyticsService.php`
- Added analytics provider resolver:
  - `app/Services/Analytics/AnalyticsSyncManager.php`
- Updated analytics sync job to route by `site.provider`:
  - `app/Jobs/SyncSiteAnalyticsMetricJob.php`
  - AWS sites -> `AwsAnalyticsService`
  - Bunny sites -> `BunnyAnalyticsService`
- Updated pages to use provider-aware data sources without renaming tabs:
  - `app/Filament/App/Pages/FirewallPage.php`
    - AWS -> `AwsFirewallInsightsService`
    - Bunny -> `BunnyFirewallInsightsService`
  - `app/Filament/App/Pages/LogsPage.php`
    - Bunny -> Bunny edge logs
    - AWS -> platform audit stream fallback
  - `app/Filament/App/Pages/CdnPage.php`
    - refresh action now syncs provider-aware analytics snapshot
  - `app/Filament/App/Pages/AnalyticsPage.php`
    - refresh action now syncs provider-aware analytics snapshot
- Replaced placeholder views with live provider-aware renderers:
  - `resources/views/filament/app/pages/protection/analytics.blade.php`
  - `resources/views/filament/app/pages/protection/cdn.blade.php`
  - `resources/views/filament/app/pages/protection/logs.blade.php`
  - `resources/views/filament/app/pages/protection/firewall.blade.php` copy adjusted for generic provider wording.
- Bunny DNS checker reliability hardening remains in place (CNAME/A/AAAA + SSL fallback) and cutover checks are crash-guarded.
- Validation snapshot after integration:
  - syntax checks passed for all new/updated service/page/job files
  - targeted tests passed:
    - `tests/Unit/BunnyCdnProviderTest.php`
    - `tests/Unit/SiteProviderSelectionTest.php`
    - `tests/Feature/ProvisionJobsTest.php`

## Onboarding + Edge Stability Updates (Latest)
- Origin input is now explicit and strict in onboarding:
  - Field label changed to `Origin / Server IP`.
  - Field is required and validates as public IPv4 only.
  - Domains are rejected for origin input to avoid CDN self-loop risks.
  - New validation rule: `app/Rules/OriginIpRule.php`.
- Create Site flow stores `origin_ip` and derives runtime origin URL as `http://<origin_ip>` for compatibility.
- Bunny provisioning now uses explicit host-header and safer origin handling:
  - Sends `OriginHostHeader = <apex_domain>` and `AddHostHeader = true`.
  - Requests free cert issuance for apex + www via Bunny API during provision/checks.
  - Auto-detects origin redirect-loop risk (HTTP->HTTPS canonical redirect to public domain) and switches origin to `https://<origin_ip>` when needed.
  - Syncs zone origin settings during provisioning and DNS/SSL checks.
- Fixed Step-4 UX crash path in Livewire:
  - Replaced hard `abort(429)` throttling with user-visible notification.
  - Prevents generic “Error while loading page” from rapid check clicks.

## Site Deletion + UX Sync (Latest)
- Added real provider-aware site deletion in App Sites table:
  - Action: `Delete site` (confirmed destructive action).
  - Performs provider cleanup first, then deletes local site record.
  - Writes audit log entries for success/failure.
- Bunny deletion now supports related multi-zone cleanup:
  - Deletes linked zone and any additional zones matching site hostnames/zone names.
- User-facing copy is fully white-labeled:
  - Uses neutral `Edge deployment` language (no provider names in dashboard messages).
- After deletion, site switcher updates without full page refresh:
  - Dispatches `sites-refreshed` event.
  - `SiteSwitcher` listens and clears stale selected site if deleted.

## Edge Telemetry + Firewall Cleanup (Latest)
- Implemented real-time forwarded edge log ingestion pipeline:
  - Added `edge_request_logs` table + `EdgeRequestLog` model.
  - Added UDP/TCP listener command (`bunny:forwarding-listen`) and Supervisor process.
  - Added `BunnyForwardedLogIngestor` to parse forwarded payloads and persist events per site.
- Fixed live ingestion parsing for Bunny forwarded logs:
  - Added support for Bunny key casing (`Host`, `RemoteIp`, `PathAndQuery`, `Timestamp`, etc.).
  - Added millisecond epoch timestamp parsing.
  - Result: live forwarded packets are now persisted in local DB and used by Firewall widgets.
- Networking hardening:
  - Opened `5514/udp` in UFW so forwarded packets can reach the listener.
- Firewall analytics now backed by local DB events when available, with existing fallback behavior retained.
- Removed Firewall page user-facing diagnostics block (Technical details widget hidden from normal UI).
- Current source of Firewall map/countries/IPs/events: local `edge_request_logs` ingestion.

## UI Cleanup (Latest)
- Removed Firewall page `Under Attack Mode` header action (Bunny flow has no supported API for this toggle).
- Removed shared top-right page buttons (`Add Site`, `Sites`) from protection pages.
- Added a single `+ Add Site` header action specifically on `Status Hub`.
- Updated App Sites table:
  - Renamed `CloudFront` column to `Zone Name`.
  - Removed `Under Attack` column.

## UI Mode + White-Label Follow-up (Latest)
- Replaced custom Simple activity feed widget markup with native Filament `TableWidget` rows:
  - added `SimpleActivityFeedTable`
  - removed old custom feed widget view/class.
- Fixed Livewire update endpoint redirects for mode switching:
  - removed unsafe full-url redirects from mode switch handlers
  - added safe return URL handling in site switcher to avoid `/livewire-.../update` targets.
- Simple/Pro topbar state sync now updates correctly across page-level mode CTA and topbar switcher events.
- Removed provider branding from user-facing copy in app panel:
  - sanitized activity/log messages (`Bunny/AWS/CloudFront` -> neutral edge wording)
  - neutralized status hub and onboarding copy to edge terminology
  - neutralized technical details labels for user-facing diagnostics.
- Firewall simple layout update:
  - simple mode now uses single-column widget grid so widgets span full width (no awkward missing slot rows).

## Alert Channels Refactor (Latest)
- Replaced Alert Channels CRUD with a single Filament-native configuration page using tabs.
- New tabs/settings sections:
  - Slack (enable, site scope, webhook URL, channel/mention overrides)
  - Email (enable, site scope, recipients, from name)
  - Phone/SMS (enable, site scope, recipients, provider note)
  - Webhook (enable, site scope, destination URL, optional secret)
- Alert Channels route remains the same (`/app/alert-channels`) and now serves a settings page.
- Data persistence remains in existing `alert_channels` table via type-based upsert (`slack`, `email`, `sms`, `webhook`).
- Removed old Alert Channels list/create/edit page classes from app panel flow.
- Fixed production 500s during rollout:
  - Updated form method signature for Filament v5 (`Schema` instead of `Form`).
  - Switched tabs component import to Filament v5 schema tabs namespace.
  - Cleared optimize caches and reloaded PHP-FPM.

## Simple vs Pro UI Mode (Latest)
- Added persistent user UI mode (`simple` | `pro`):
  - DB: `users.ui_mode` (default `simple`)
  - Runtime: session-backed via `UiModeManager`
- Added App topbar mode switcher near site switcher:
  - `Simple` / `Pro` segmented controls
  - instant notification and state update
- Added centralized mode service and page helpers:
  - `App\Services\UiModeManager`
  - `BaseProtectionPage` helpers: `isSimpleMode()`, `isProMode()`, `switchToProMode()`
- Added bandwidth usage support (with >=80% warning):
  - config limits in `config/ui.php`
  - `BandwidthUsageService`
  - `BandwidthUsageStats` widget
- Added human-friendly activity feed (cached short TTL):
  - `ActivityFeedService` from `edge_request_logs` + `audit_logs`
  - `SimpleActivityFeedWidget`
- Mode-aware presentation updates:
  - Overview, Firewall, CDN, Cache, Analytics, Logs, SSL, Status Hub now show simplified content in Simple mode with CTA to switch to Pro.
  - Pro mode shows advanced views/charts/tables/logs.
  - Technical details drawer hidden in Simple mode.
- Redirect hardening:
  - Fixed mode switch redirect leak to Livewire update endpoint.
  - Hardened site switcher return URL handling to avoid `/livewire-.../update` targets.
- Added tests:
  - `tests/Feature/UiModeTest.php` covering default mode, persistence/session switching, and simple/pro visibility behavior.
- Validation snapshot:
  - `./vendor/bin/pint --dirty` passed
  - `php artisan test` passed
  - `php artisan optimize` passed
- Backup branch created before rollout:
  - `backup/pre-ui-mode-20260227-202412`
