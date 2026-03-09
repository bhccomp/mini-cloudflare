# MEMORY

## Local Credentials Convention
- Sensitive cross-project credentials should be stored in `.local/CREDS.md`.
- `.local/` is git-ignored and must remain local-only.
- Intended use:
  - SSH connection details for external WordPress/plugin servers
  - repo URLs or deployment paths that should not be committed
- Do not commit, stage, or upload `.local/CREDS.md`.
- Current external WordPress server context is tracked there, including:
  - host `5.78.113.107`
  - user `codex`
  - WordPress path `/var/www/nodesfoundry.com`

## GitHub Push Environment Note
- In this environment, GitHub operations for the Laravel app repo may fail with `Could not resolve host: github.com` when run through the restricted command path.
- If a Laravel repo `git push` or `git ls-remote` fails that way, retry it with unrestricted/escalated execution rather than assuming the repo remote or credentials are broken.
- Confirmed behavior:
  - restricted Laravel `git push` failed with DNS resolution error
  - unrestricted/escalated Laravel `git ls-remote` and `git push` succeeded

## WordPress Plugin Repo Workflow
- The WordPress plugin lives in a separate repo:
  - GitHub repo: `https://github.com/bhccomp/firephage-security`
- Local development workspace on this server:
  - `/var/www/firephage-security`
- Remote WordPress plugin path:
  - `/var/www/nodesfoundry.com/wp-content/plugins/firephage-security`
- Remote Git auth is already configured under the `codex` user on `5.78.113.107`.
- Working process for plugin changes:
  - make plugin changes in the separate local repo at `/var/www/firephage-security`
  - commit and push that repo to GitHub
  - SSH to `5.78.113.107`
  - pull the latest plugin changes in `/var/www/nodesfoundry.com/wp-content/plugins/firephage-security`
- Operational expectation:
  - when plugin work is requested from this FirePhage workspace, sync the remote WordPress plugin repo after local plugin changes so the updated plugin code is present on `nodesfoundry.com`
  - plugin activation/setup inside WordPress may still be a separate step when needed
- Process note:
  - each time plugin work is requested from this workspace, read `/var/www/firephage-security/MEMORY.md` first before implementing changes

## FirePhage Public Checksum Cache (Latest)
- Added a public checksum cache API so the WordPress plugin can verify WordPress.org plugin/theme packages without each site hitting WordPress.org directly on every lookup.
- New API route:
  - `GET /api/plugin/checksums?type=plugin|theme&slug=...&version=...`
- New persistence:
  - migration `database/migrations/2026_03_08_120000_create_package_checksum_caches_table.php`
  - model `app/Models/PackageChecksumCache.php`
- New service/controller:
  - `app/Services/WordPress/WordPressChecksumCacheService.php`
  - `app/Http/Controllers/Api/PackageChecksumController.php`
- Behavior:
  - cache by package `type + slug + version`
  - fetch from WordPress.org on miss/stale entry
  - return cached checksums on future requests
  - return stale cached checksums if WordPress.org fetch fails after a successful prior fetch
- Product/compliance intent:
  - checksum cache remains available to free plugin users
  - no paid FirePhage connection is required for checksum lookups
  - endpoint is for public checksum metadata only, not site registration or paid dashboard sync
- Plugin integration follow-up:
  - added plugin connection tables for one-time connection tokens and installed site credentials
  - added plugin routes:
    - `POST /api/plugin/connect`
    - `POST /api/plugin/report`
    - `GET /api/plugin/firewall-summary`
    - `GET /api/plugin/performance-summary`
  - these use a site-scoped bearer token and currently support connected WordPress plugin installs with read-only Firewall/Performance tab summaries
  - added a dedicated `WordPress` app page:
    - unconnected state shows plugin connection instructions and one-time token generation
    - connected state shows plugin connection status, last seen/report, WordPress health summary, malware scan summary, update exposure, and recent findings from the latest plugin report
  - removed the temporary plugin connection UI from `Site Status Hub` so WordPress/plugin lifecycle has its own surface
  - the `WordPress` page now owns plugin token generation instead of `Site Status Hub`
  - the `WordPress` navigation item now uses a custom WordPress logo from `public/images/wordpress-menu.png` via the local Blade Icons set in `config/blade-icons.php`

## Edge Routing Drift Warning + Status Mapping (Latest)
- Added live routing drift detection so the app can identify when a protected site is no longer pointed to the expected edge target:
  - New service: `app/Services/Sites/SiteRoutingStatusService.php`
  - Checks apex and optional `www` hostnames against the expected traffic target.
  - Resolves CNAME/A/AAAA records and compares them to the current edge target.
  - Results are cached for 60 seconds and can be manually refreshed from the UI.
- Added a reusable DNS drift warning UI:
  - New banner partial: `resources/views/filament/app/pages/protection/edge-routing-warning.blade.php`
  - Uses warning/danger alert styling instead of a standard settings widget.
  - DNS recovery records are hidden behind an expandable details block (`Show DNS records to restore protection`).
- Added a Filament page-header wrapper so the warning renders above page widgets on the two pages that use header widgets:
  - New header partial: `resources/views/filament/app/pages/protection/page-header-with-routing-warning.blade.php`
  - Wired into:
    - `app/Filament/App/Pages/FirewallPage.php`
    - `app/Filament/App/Pages/SiteStatusHubPage.php`
  - This avoids Filament render-order issues where body content appears below header widgets.
- Warning banner rollout:
  - Added to Status Hub, Protection Overview, Dashboard, CDN, Cache, Origin, Analytics, Availability Monitor, WAF Access Control, Rate Limiting, DDoS/Shield settings, Logs, and SSL pages.
  - Removed the earlier `Edge Routing Status` widget/section approach in favor of the banner.
- Shared page status is now routing-aware for live sites:
  - `app/Filament/App/Pages/BaseProtectionPage.php` now maps live routing drift to:
    - `Protection Inactive` (`danger`)
    - `Partially Protected` (`warning`)
  - This status mapping is used by page badges and the site picker empty-state list.
- Sites table now surfaces routing drift as a first-class status:
  - `app/Filament/App/Resources/SiteResource.php`
  - Live sites that are no longer pointed correctly no longer show as plain `Active`.
- Status Hub lifecycle logic was corrected after an early regression:
  - `SiteStatusHubPage` now separates:
    - `isSiteLive()` -> lifecycle/onboarding state
    - `isLiveProtected()` -> lifecycle state plus correct routing
  - This prevents routing drift warnings from collapsing the whole page into onboarding-only content.
- Added tests:
  - `tests/Unit/SiteRoutingStatusServiceTest.php`
- Operational validation:
  - Confirmed drift detection live against `nikolajocic.dev` after DNS was repointed away from the expected edge target.
  - Repeated `php artisan optimize:clear` runs were needed while iterating on Filament view placement/caching.

## Bunny Shield Advanced + Troubleshooting Mode + Strict Site Deletion (Latest)
- Reframed Bunny custom-page behavior:
  - Origin custom error pages are no longer part of active Bunny onboarding/provisioning flow.
  - `app/Services/Edge/Providers/BunnyCdnProvider.php` now records origin custom pages as inactive/disabled instead of auto-attaching middleware during provisioning.
  - Existing shared script/service code remains in the repo, but the platform now treats Shield/WAF pages and origin pages separately.
- Added Bunny Shield Advanced onboarding hook:
  - New config flags in `config/edge.php`:
    - `bunny.shield_auto_upgrade_to_advanced`
    - `bunny.shield_advanced_plan_type`
  - New service method in `app/Services/Bunny/BunnyShieldSecurityService.php`:
    - `ensureAdvancedPlan(Site $site, ?int $shieldZoneId = null)`
  - Bunny provisioning now upgrades Shield to Advanced immediately after shield-zone creation/resolution when the feature flag is enabled.
  - Provider meta now records Shield plan status/message and saved premium-plan state.
- Added safer Bunny delete semantics:
  - `app/Services/Edge/Providers/BunnyCdnProvider.php` delete flow now:
    - downgrades/cancels Bunny Shield premium plan before teardown
    - deletes related Bunny pull zones
    - verifies matching pull zones are actually gone
    - verifies Shield premium plan is downgraded (or the shield zone is gone) before allowing local deletion
    - does not require the Shield zone object itself to disappear, because Bunny public API docs expose create/read/update flows for shield zones but do not clearly document a shield-zone delete endpoint
  - Local site deletion now uses explicit cleanup service:
    - `app/Services/Sites/SiteDeletionService.php`
    - Deletes site-scoped audit logs, alert channels, analytics, firewall rules, events, availability checks, edge request logs, and clears `users.selected_site_id` before removing the site row.
- Added no-DNS-change troubleshooting mode:
  - New persisted site flag:
    - `sites.troubleshooting_mode`
    - migration: `database/migrations/2026_03_06_210000_add_troubleshooting_mode_to_sites_table.php`
  - New job:
    - `app/Jobs/ToggleTroubleshootingModeJob.php`
  - New Bunny behavior:
    - enables Bunny development mode (cache/optimizer relaxation)
    - disables Bunny Shield WAF while preserving a snapshot for later restoration
    - implemented via:
      - `BunnyCdnProvider::setTroubleshootingMode(...)`
      - `BunnyShieldSecurityService::setTroubleshootingMode(...)`
  - UI surfaced on visible user pages:
    - WAF Overview header actions (`Sync now`, `Enable/Disable Troubleshooting Mode`, `Open WAF`, `Open DDoS`)
    - Status Hub page section
- CDN/Cache page timeout mitigation:
  - `app/Filament/App/Pages/CdnPage.php` and `app/Filament/App/Pages/CachePage.php` no longer fetch live Bunny logs during page render.
  - Both pages now read from local `edge_request_logs` to avoid Bunny API timeouts causing UI 504s.
- Validation completed:
  - `php artisan test tests/Unit/BunnyCdnProviderTest.php`
  - `php artisan test tests/Unit/SiteDeletionServiceTest.php`

## Bunny Edge Error Pages + Shared Middleware (Latest)
- Added a shared Bunny Edge Scripting workflow for branded edge-served error pages on new Bunny sites.
- New service: `app/Services/Bunny/BunnyEdgeErrorPageService.php`
  - Renders repo-backed templates into HTML strings.
  - Builds shared middleware source that branches on response status and injects the current hostname at runtime.
  - Syncs the shared Bunny script and persists `edge_error_script_id` under the existing `bunny` system setting.
- Added repo-backed default error templates under `resources/views/edge-errors/`:
  - `not-found` (`404`)
  - `forbidden` (`403`)
  - `rate-limited` (`429`)
  - `unavailable` (`500/502/503/504`)
- Bunny provisioning now auto-attaches the shared `EdgeScriptId` to each new Pull Zone:
  - Implemented in `app/Services/Edge/Providers/BunnyCdnProvider.php`
  - Provider meta now records script id/status/error/sync timestamp.
- Added console command:
  - `php artisan bunny:sync-edge-error-pages`
  - Optional `--attach` to backfill existing Bunny zones.
- Preserved attached `EdgeScriptId` during later Bunny zone updates so development-mode / origin sync changes do not clear the middleware accidentally.
- Registered console command discovery in `bootstrap/app.php` so app console commands under `app/Console/Commands` are available without extra manual wiring.
- Validation completed:
  - `./vendor/bin/pint --dirty`
  - `php artisan test tests/Unit/BunnyEdgeErrorPageServiceTest.php tests/Unit/BunnyCdnProviderTest.php`

## Home Variant 1 Conversion + Rhythm Fixes (Latest)
- Scope constrained to `home-variant-1` only (no changes to other variants/shared marketing layout templates).
- Added variant-specific hero and onboarding components:
  - `resources/views/components/marketing/hero-variant-1.blade.php`
  - `resources/views/components/marketing/human-friendly-onboarding-variant-1.blade.php`
- Hero improvements for clarity and conversion:
  - Stronger non-technical positioning copy for website owners.
  - Added supporting compatibility line (WordPress, WooCommerce, Laravel, APIs, SaaS).
  - Added lightweight “Perfect for” micro-block inside hero.
  - Increased hero laptop visual prominence and added grounded glow treatment.
- Added variant-only monitoring proof/features:
  - New section component `resources/views/components/marketing/availability-monitoring-variant-1.blade.php`
  - Includes availability bullets, integrations row (`Slack`, `Email`, `SMS`, `Webhooks`), and helper copy.
  - Added uploaded image asset integration: `public/design-assets/monitor-alerts.png`.
- Monitoring section restyled to match feature-section pattern:
  - Removed outer oversized boxed panel.
  - Kept clean 2-column structure with subtle image glow/shadow.
- Vertical rhythm cleanup iteration:
  - Implemented stricter variant-scoped section spacing rules.
  - Removed conflicting duplicate spacing rule blocks in `home-variant-1`.
  - Applied explicit tighter spacing for the monitoring section to prevent excessive whitespace.
  - Hero bottom spacing adjusted to preserve background continuity (space inside hero instead of external gap block).
- Operational:
  - Repeated cache/build refresh during tuning (`php artisan optimize`, `pnpm build`) to ensure changes render immediately.

## Marketing Variants + Transparent Illustration Rebalance (Latest)
- Added three clone landing pages for rapid visual/theme A/B/C comparison:
  - Routes:
    - `/home-variant-1` (`home.variant1`)
    - `/home-variant-2` (`home.variant2`)
    - `/home-variant-3` (`home.variant3`)
  - Views:
    - `resources/views/marketing/home-variant-1.blade.php`
    - `resources/views/marketing/home-variant-2.blade.php`
    - `resources/views/marketing/home-variant-3.blade.php`
- Added/updated design assets in `public/design-assets/` and re-used componentized marketing sections across all variants.
- Balanced transparent PNG illustrations that became visually too small after background removal:
  - Added reusable CSS illustration utilities in `resources/css/app.css`:
    - `.feature-illustration`
    - `.feature-illustration--onboarding`
    - `.feature-illustration--dns`
    - `.feature-illustration--laptop`
    - `.feature-illustration--map`
  - Added explicit image dimensions (`1536x1024`) on key section `<img>` tags to reduce layout shift.
  - Increased desktop presence for onboarding, DNS cutover, dashboard laptop, and map illustrations with responsive max-width rules.
  - Added subtle drop-shadow/glow for transparent assets so they remain readable on dark backgrounds.
- Global Edge Protection map spacing tweak:
  - Shifted map visual left on XL (`translateX(-18%)`) to reduce gap vs adjacent text and align with other section composition.
- Operational:
  - Rebuilt frontend assets repeatedly after CSS changes (`pnpm build`).
  - Refreshed Laravel caches (`php artisan optimize`, `optimize:clear`) to ensure new visuals are served immediately.

## Landing Page Flow, Section Rhythm, and Mobile Navigation (Latest)
- Marketing landing page structure was iterated heavily to reduce the “single poster” effect and improve scan flow:
  - Added strict alternating section backgrounds from post-hero through pricing with subtle separators:
    - `A = bg-[#020817]`, `B = bg-[#041427]`
    - Each section now uses `border-y border-white/5` and a subtle gradient overlay for transition clarity.
  - Introduced **Edge Protection in Numbers** section (4 metrics cards) to break up consecutive large illustration blocks.
  - Reordered layout so **Core Security Capabilities** sits between large illustration sections for better rhythm.
- Illustration/content composition updates:
  - Converted **Global Edge Protection** and **How FirePhage Protects Your Infrastructure** into split desktop layouts:
    - Global Edge Protection: text left, illustration right.
    - Infrastructure Protection: illustration left, text right.
  - Replaced both sections with human-readable paragraphs + bullet lists.
  - Increased diagram sizing to improve presence and reduce empty vertical feel.
- Pricing hierarchy improvements:
  - Featured middle plan now visually dominant (`scale-105`, stronger cyan border, `shadow-lg`).
  - Added **Most Popular** badge to featured plan.
  - CTA buttons standardized to full width with stronger hover states.
- Mobile navigation:
  - Added responsive hamburger (“sandwich”) menu for small screens on the marketing header.
  - Desktop nav remains visible on `md+`; hamburger + dropdown menu only on small screens.
  - Mobile menu closes on link click and `Escape`.
- Added/updated marketing assets and components used during redesign iterations:
  - New component: `resources/views/components/marketing/edge-protection-numbers.blade.php`
  - Additional local image assets staged for marketing experiments:
    - `banner.png`, `new-banner.png`, `white-banner.png`, `draft.png`, `waf_overview.png`
    - `public/images/hero-banner.png`, `public/images/hero-banner-light.png`

## Shield Phage Branding + Human-Readable Marketing Refresh (Latest)
- Adopted selected logo direction: **Shield Phage**.
  - Added reusable logo assets:
    - `public/images/logo-shield-phage-mark.svg`
    - `public/images/logo-shield-phage-wordmark.svg`
  - Public marketing header now uses icon + inline `FirePhage` text.
  - Filament App/Admin panels now use Shield Phage wordmark via `brandLogo(...)`.
  - Added marketing logo concept board page at `/logos`:
    - Route: `Route::view('/logos', 'marketing.logos')->name('logos')`
    - View: `resources/views/marketing/logos.blade.php`
- Marketing messaging reworked for non-technical audience (hero layout/background intentionally left as-is for now):
  - Added new section under hero:
    - **Free Assisted Onboarding (We&apos;ll handle DNS for you)**
    - Includes plain-language explanation, practical bullets, and CTA.
  - Added new section near dashboard preview:
    - **A dashboard built for humans**
    - Highlights plain-language summary, top sources, simple actions, useful alerts, and example message callout.
  - Replaced threat explanation block with:
    - **Why sites get hit (even when you think you&apos;re protected)**
    - Human-readable bullets and simple closing statement.
  - Updated How It Works Step 2 to explicitly state:
    - FirePhage can handle DNS setup for free.
  - Pricing updated to make free assisted onboarding highly visible:
    - Added prominent onboarding line above plans and repeated line inside each plan card.
- Marketing copy cleanup:
  - `origin exposure detection` -> `origin IP protection` (marketing contexts).
  - Added first-use clarification: `WAF (Firewall rules)`.
  - Switched `controls` -> `settings` where clearer for general audience.

## Demo Site Seeding + Marketing Screenshot Data (Latest)
- Added isolated demo site for screenshots:
  - Domain: `example.com` (site id `495`)
  - Seeded with high-volume analytics and firewall-style traffic for marketing captures.
  - Real production site `nikolajocic.dev` remains on real data and was not modified by demo overrides.
- Seeded/adjusted demo metrics for screenshot targets:
  - Firewall summary target values applied for demo: total `239,518`, blocked `38,242`, suspicious `12,402`.
  - Map/country emphasis: US requests boosted and overrideable (`92,563` target used for captures).
  - Top IP table now supports demo override rows with high request/blocked counts.
- Demo “Last Sync” is now forced to show `1 minute ago` continuously for demo-seeded sites in UI widgets/pages.
- Added demo-aware insight override hooks (gated by `site.provider_meta.demo_seeded = true`):
  - `demo_suspicious_requests_24h`
  - `demo_block_ratio`
  - `demo_suspicious_ratio`
  - `demo_top_countries`
  - `demo_top_ips`
- Overview parity fix:
  - Security & Protection overview summary can use seeded analytics totals for demo sites so numbers align across Overview/Firewall/Analytics.
- Threat label update (global UI copy):
  - Replaced threat level text `Degraded` -> `Active Mitigation`.
- Added `Site` helper methods for demo-aware sync freshness:
  - `isDemoSeeded()`
  - `syncFreshnessForHumans($fallback)`
  - Wired into Firewall Threat Summary widget and protection pages (`Analytics`, `CDN`, `Cache`).

## Global Topbar AJAX Search (Latest)
- Added a new global AJAX search in App panel topbar (right side, near avatar) using Livewire + Filament dropdowns.
- Search scope includes:
  - App pages (Status Hub, Overview, WAF, DDoS, Rate Limiting, SSL/TLS, CDN, Cache, Origin, Analytics, Availability Monitor)
  - App resources (Sites, Alert Rules, Alert Channels, Alert Events)
  - Organization sites (with status badges)
- Added Livewire component:
  - `app/Livewire/Filament/App/GlobalSearch.php`
- Added views:
  - `resources/views/livewire/filament/app/global-search.blade.php`
  - `resources/views/filament/app/components/topbar-global-search.blade.php`
- Wired with Filament render hook:
  - `PanelsRenderHook::TOPBAR_END` in `app/Providers/Filament/UserPanelProvider.php`
- UX behavior:
  - Debounced live query
  - Click result navigates immediately (site results open Status Hub with `site_id`)
  - Provider-neutral labels and Filament-native styling


## Public Website + Auth Entry Routing + Conversion Landing (Latest)
- Root route is now a public marketing site (no redirect to `/app`).
- Auth routing behavior:
  - `/app` remains dashboard entry and protected.
  - Unauthenticated `/app` requests now redirect to `/login`.
  - `/login` redirects to Filament app login at `/app/login`.
  - `/register` currently routes to `/app/login` (safe auth entrypoint).
- Added middleware `RedirectUnauthenticatedAppToLogin` and wired it into web + app panel middleware stack.
- Added componentized marketing frontend under `resources/views/components/marketing/*` and `resources/views/marketing/*`:
  - Hero updated with strong positioning copy and proof strip.
  - Dashboard Preview section now uses a real dashboard screenshot in a framed card style.
  - Removed preview zoom/lightbox interactions; screenshot is now static with no click/fullscreen effects.
  - Added optimized public preview assets:
    - `public/images/dashboard-preview.webp`
    - `public/images/dashboard-preview.png`
  - Problem section tightened to concrete pain points.
  - Features rewritten with specific capabilities (country/IP/CIDR, rate limiting, origin exposure/health, anomaly detection, alerts).
  - Pricing clarified by tier with concrete limits and CTA destinations.
  - Added credibility section and contact page (`/contact`).
  - Footer includes login/contact links and placeholders for terms/privacy.
- SEO/OG tags updated for stronger positioning on homepage.
- Avatar media fix: created missing `public/storage` symlink so uploaded profile avatars load correctly.
- Profile page UX fix: added Breezy view overrides to right-align update buttons and increase vertical spacing between sections/forms.
## Security & Protection Navigation + Shield + Availability Monitor (Latest)
- Shield onboarding and existing-site linkage:
  - Added auto ensure/link of Shield zone during Bunny provisioning.
  - Added fallback ensure on DNS checks so delayed Shield creation is retried automatically.
  - Backfilled existing production site (`nikolajocic.dev`) and persisted `provider_meta.shield_zone_id`.
- Fixed country/IP rule enforcement against current Bunny Shield API:
  - Access-list payload updated to new schema (`name`, `type`, `content`, `action`, `isEnabled`).
  - Correct type mapping discovered and applied (`country` => type `3`).
  - Improved error extraction (`error.message`, validation payloads) and surfaced exact failures in UI.
- Added Shield settings service and pages:
  - `WAF` page (`/app/firewall-access-control`) for access rules and policy controls.
  - `DDoS` page (`/app/firewall-shield-settings`) for sensitivities + challenge window.
  - `Rate Limiting` page (`/app/firewall-rate-limiting`) for creating/listing rate limit rules.
- Navigation UX refactor (flat, non-nested):
  - Reorganized App sidebar into:
    - `General`: Status Hub, Sites, Origin
    - `Security & Protection`: Overview, WAF, DDoS, Rate Limiting, SSL/TLS
    - `Performance`: CDN, Cache
    - `Monitoring`: Availability Monitor, Analytics
    - `Account`: Billing, Organization Settings
    - `Alerts`: Alert Channels, Alert Events, Alert Rules
  - Applied native Filament navigation groups with collapsed sections by default.
- Added Availability Monitor feature (Filament-native):
  - New page `/app/availability-monitor` + recent checks table widget.
  - New persistence model/table: `site_availability_checks`.
  - Plan-aware check cadence:
    - Basic plans: every 5 minutes.
    - Paid plans: every 1 minute.
  - Added scheduled command `availability:run-due` (every minute) with migration guard.
  - Added config keys in `config/ui.php` for monitor intervals and paid-plan mapping.
- Stability and test status:
  - Cleared/cached framework + Filament artifacts repeatedly to eliminate route cache mismatch issues.
  - Validation completed: `./vendor/bin/pint --dirty`, `php artisan test` (all passing), `php artisan optimize`.
  - Migration applied: `2026_03_02_230000_create_site_availability_checks_table`.

## Firewall Access Control + Provisioning Safety (Latest)
- Added a new Firewall Access Control flow for App panel:
  - Page: `/app/firewall-access-control`
  - Supports country, continent, IP/CIDR, bulk import, staged mode, and policy flags.
  - Includes rules table widget and quick IP block from recent firewall events.
- Added firewall rules persistence model/table:
  - `site_firewall_rules` migration + model `SiteFirewallRule`.
- Added Bunny Shield access-list integration service:
  - Resolves shield zone, manages access-list rules, loads countries/continents with safe fallbacks.
- Added access-control orchestration service:
  - Create/apply/remove/deploy/expire rule operations + audit logging.
- Fixed 500/page-load crash when saving rule set:
  - Root cause: uncaught runtime exception when shield zone is unavailable.
  - Rule apply now catches provider exceptions, marks status `failed`, stores error in `meta`, and sends warning notification instead of crashing Livewire.
- Added explicit rule status constant: `SiteFirewallRule::STATUS_FAILED`.
- Countries list reliability improved:
  - Added multiple Bunny endpoint fallbacks and local ICU country fallback.
- Navigation refactor for Firewall section:
  - Single left sidebar structure retained with nested Firewall items (`Overview`, `Access Control`), removed in-content sub-sidebar behavior.
- Safety fix to prevent live provider side effects in feature/unit tests:
  - `CreateSite::afterCreate()` now skips auto-provisioning when `app()->runningUnitTests()`.
  - Prevents accidental real Bunny zone creation from test fixtures (e.g. `wizard-example.com`, `203.0.113.10`) when real API key exists.
- Operational notes:
  - Existing fake Bunny zones were caused by direct provisioning in test execution context; this is now guarded.

## Dev Mode + Navigation Cleanup (Latest)
- Added real per-site `development_mode` support:
  - migration `2026_02_28_000000_add_development_mode_to_sites_table.php`
  - `sites.development_mode` is now persisted and cast on `Site` model.
- Added provider contract method `setDevelopmentMode(Site $site, bool $enabled)` and implementations:
  - Bunny: updates pull zone settings to disable cache/optimization in development mode.
  - AWS: compatible no-break response path for mode toggling.
- Added `ToggleDevelopmentModeJob` with audit logging (`edge.development_mode`).
- Wired Development Mode toggle into `/app/cdn` and `/app/cache` actions and status rows.
- Updated toggle execution in page flow to run immediately and refresh site state, so button label flips instantly between enable/disable.
- Fixed CDN 500 caused by malformed provider log timestamps:
  - `BunnyLogsService` now safely parses epoch/pipe-formatted timestamps and falls back safely instead of crashing.
- Hid Logs page from sidebar navigation while keeping route/page accessible:
  - `LogsPage::$shouldRegisterNavigation = false`.
- Moved Sites resource nav group from `Security` to `Protection` so Security header is removed in app sidebar.
- Validation snapshot:
  - `php artisan migrate --force` passed
  - `./vendor/bin/pint --dirty` passed
  - `php artisan test` passed
  - `php artisan optimize` passed

## Branding Asset Update (Latest)
- Added a real FirePhage favicon set in `public/`:
  - `favicon.svg`
  - `favicon.ico` (replaced previously empty file)
  - `favicon-16x16.png`
  - `favicon-32x32.png`
  - `favicon.png`
  - `apple-touch-icon.png`
- Wired favicon into both Filament panels via provider config:
  - `app/Providers/Filament/UserPanelProvider.php`
  - `app/Providers/Filament/AdminPanelProvider.php`
- Result: consistent favicon across `/app` and `/admin` with proper browser fallback handling.

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

## Country Block Deployment Verification (Latest)
- Ran live Bunny API verification for `nikolajocic.dev` after Serbia block report.
- Confirmed active edge/shield resources:
  - Pull zone: `fp-275-nikolajocic-dev` (`id=5392977`)
  - Shield zone: `85227`
- Confirmed custom country access list is deployed and enabled:
  - `listId=27764`
  - `isEnabled=true`
  - `action=4` (block)
  - `type=3` (country)
  - deployed content includes `RS` (Serbia), plus `AF`, `AD`, `AZ`.
- Confirmed domain traffic is served via edge network (response headers include `server: BunnyCDN-*` and `cdn-pullzone: 5392977`).
- Practical conclusion for user test mismatch:
  - rule is deployed on provider side;
  - continued access is most likely due to request geolocation not resolving to `RS` from tester network path (VPN/ISP/mobile egress).

## Firewall Access Rule UX + Action Mapping (Latest)
- Improved grouped rule naming for Access Control table and persisted rule targets:
  - replaced legacy labels like `Country set (4)` / `Rule set (N)` with friendly labels.
  - new labels are action-aware, e.g. `Country Blocks`, `Country Allowlist`, `Country Challenges`.
  - added display fallback so legacy rows render friendly names without DB migration.
- Corrected Bunny Shield action code mapping to match 5-mode order:
  - `bypass=0`, `allow=1`, `block=2`, `challenge=3`, `log=4`.
- Live fix applied to current custom list so it is in block mode (`action=2`) instead of allow/log.

## Global Content Width Consistency (Latest)
- Fixed inconsistent widths across App panel pages (Simple + Pro) by removing custom hard caps (`max-width: 72rem`) from shared protection wrappers.
- Updated wrappers now follow Filament page content width uniformly, so top sections and lower widgets/tables align on the same width.
- Files adjusted:
  - `resources/views/components/filament/app/settings/layout-styles.blade.php` (`.fp-protection-shell`)
  - `resources/views/filament/app/pages/site-status-hub.blade.php` (`.fp-status-shell`)
- Refreshed optimized caches after change (`php artisan optimize`).

## Status Hub Live Dashboard Refresh (Latest)
- Refactored Status Hub behavior after onboarding completes (`Live / Protected`):
  - onboarding/provisioning flow is hidden once site is live.
  - page now acts as a compact dashboard.
- Added a clear Simple vs Pro difference on live status hub:
  - Simple: compact high-level cards only (`SiteSignalsStats`, `BandwidthUsageStats`).
  - Pro: advanced analytics-focused charts only (no WAF map/tables):
    - `Regional Traffic Share`
    - `Cache Delivery Split`
    - `Regional Threat Profile`
    - `Security Posture Trend` (new)
- Removed WAF-heavy widgets from Status Hub Pro view (map, attack breakdown, top countries, top IPs).
- Removed top Status Hub hero section/card in live mode to reduce page length.
- Layout tuning for Pro view:
  - enforced two-column paired rows:
    - `Regional Traffic Share` + `Cache Delivery Split`
    - `Regional Threat Profile` + `Security Posture Trend`
- Visual consistency fix:
  - implemented Filament widget equal-height behavior for side-by-side cards.
  - added fixed chart canvas height for no-aspect-ratio chart widgets to prevent uneven pair heights.
- Cleanup:
  - removed unused interim widget file `StatusHubLiveServiceStats`.

## Bunny Analytics Accuracy Fix (Latest)
- Resolved mismatch where dashboard showed `Blocked Requests (24h) = 0` despite blocked/challenge actions existing in ingested edge logs.
- Root cause:
  - Bunny analytics sync path was relying on API/fallback event source that did not fully reflect local ingested security actions.
- Implemented fix in `BunnyAnalyticsService`:
  - prefer local `edge_request_logs` events for blocked/allowed/trend/regional calculations;
  - fallback to remote recent logs only when local events are unavailable.
- Bandwidth usage source corrected:
  - added pull-zone lookup to fetch real `MonthlyBandwidthUsed` bytes;
  - persisted to `site_analytics_metrics.source.monthly_bandwidth_bytes` and `monthly_bandwidth_gb`;
  - this avoids request-based estimated monthly usage where live bytes are available.
- Live verification performed for `nikolajocic.dev` (site `275`):
  - metrics now persist non-zero blocked values from real logs;
  - source now includes monthly bandwidth bytes from Bunny zone API.

## User Profile Features via Filament Plugin (Latest)
- Installed `jeffgreco13/filament-breezy` to avoid custom account-settings implementation.
- Enabled Breezy plugin on App panel (`UserPanelProvider`) with My Profile page:
  - user menu link enabled (`My Profile`)
  - navigation item enabled under `Account`
  - avatar upload enabled (`hasAvatars: true`)
- Added avatar support to `User` model:
  - implements `Filament\Models\Contracts\HasAvatar`
  - `getFilamentAvatarUrl()` returns storage URL for `avatar_url`
  - added `avatar_url` to fillable fields
- Added DB migration for `users.avatar_url` and ran migrations.
- Result: users can manage profile info (name/email), password, and avatar through plugin-provided My Profile page at `/app/my-profile`.

## Home Variant 1 Copy + Pricing Refresh (Latest)
- Scope constrained to `home-variant-1` only (no shared layout/other variants changed).
- Hero updates in `hero-variant-1`:
  - headline set to: `Stop Attacks Before They Reach Your Server`
  - subheadline updated to plain-language value proposition:
    - `FirePage shields your origin at the global edge.`
    - `No infrastructure to manage. Pay only for what you use.`
  - secondary CTA label updated to `View live demo dashboard`
  - added centered trust line under hero badges:
    - `Already protecting 40+ websites • 3.2 million requests filtered last month • 99.9% attack mitigation`
- Onboarding CTA text update in `human-friendly-onboarding-variant-1`:
  - `Start onboarding` -> `Start protecting now`
- Added variant-specific architecture component:
  - `platform-architecture-variant-1.blade.php`
  - title changed to `How FirePage Protects You Today`
  - first sentence changed to:
    - `Every request passes through FirePage's global edge network where we automatically filter malicious traffic before it ever touches your origin server.`
- Added variant-specific pricing component:
  - `pricing-variant-1.blade.php`
  - replaced pricing content with Starter / Growth (Most Popular) / Enterprise plan copy
  - added note under cards:
    - `No setup fees • Cancel anytime • Built while we validate and scale`
- Added variant-specific footer component:
  - `footer-variant-1.blade.php`
  - footer text set to: `© 2025 FirePage. All rights reserved.`
- Wired `home-variant-1.blade.php` to include variant-specific components:
  - platform architecture -> `platform-architecture-variant-1`
  - pricing -> `pricing-variant-1`
  - footer -> `footer-variant-1`

## WordPress-Focused Homepage Copy Update (Latest)
- Applied text-only updates on current homepage (`/`, serves `home-variant-1`).
- Hero copy now WordPress-specific (headline + subheadline) with trust line updated to “40+ WordPress websites”.
- “Perfect For” badges updated to: WordPress websites, WooCommerce stores, Agency-managed sites, High-traffic blogs.
- Global Edge Protection first sentence updated to mention custom WordPress WAF rules.
- Platform architecture first sentence updated to custom WordPress WAF rules.
- Pricing copy adjusted:
  - Growth plan alerts include Webhook.
  - Enterprise plan includes “Custom WordPress security rules”.
- Footer already correct: `© 2025 FirePage. All rights reserved.`
- Files touched:
  - `resources/views/components/marketing/hero-variant-1.blade.php`
  - `resources/views/components/marketing/global-edge-protection.blade.php`
  - `resources/views/components/marketing/platform-architecture-variant-1.blade.php`
  - `resources/views/components/marketing/pricing-variant-1.blade.php`

## WordPress Plugin Signature Feed (Latest)
- Added public plugin signature endpoint:
  - `GET /api/plugin/signatures`
- Endpoint returns data-only malware signature metadata for the WordPress plugin:
  - high-confidence regex patterns
  - weighted heuristic patterns
  - feed version + fetched timestamp
- Signatures are defined in:
  - `config/firephage-wordpress-signatures.php`
- Controller/service added:
  - `app/Http/Controllers/Api/PluginSignatureController.php`
  - `app/Services/WordPress/WordPressMalwareSignatureService.php`
- Intended plugin behavior:
  - fetch signature feed from FirePhage
  - cache locally
  - merge with bundled fallback signatures
  - continue scanning if FirePhage feed is unavailable

## Homepage Route Promotion + Variant Cleanup (Latest)
- Promoted `home-variant-1` to the primary homepage route:
  - `/` now serves `marketing.home-variant-1`.
- Preserved previous blue homepage as alternate route:
  - `/blue-alternative` now serves `marketing.home`.
- Removed extra variant pages from routing and views:
  - deleted `resources/views/marketing/home-variant-2.blade.php`
  - deleted `resources/views/marketing/home-variant-3.blade.php`
- Cleared route cache and rebuilt optimize cache to apply URL changes immediately.
