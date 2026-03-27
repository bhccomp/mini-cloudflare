<x-filament-panels::page>
    <x-filament.app.settings.layout-styles />

    @php($trend = $this->cacheHitTrend())
    @php($misses = $this->topCacheMisses())
    @php($history = $this->purgeHistory())
    @php($cacheEnabled = $this->isCacheEnabled())
    @php($cacheMode = ucfirst($this->cacheMode()))
    @php($developmentMode = $this->isDevelopmentMode())
    @php($troubleshootingMode = $this->isTroubleshootingMode())
    @php($cacheExclusions = $this->cacheExclusions())
    @php($browserCacheLabel = $this->browserCacheTtlLabel())
    @php($queryStringLabel = $this->queryStringPolicyLabel())
    @php($nextCacheMode = strtolower($cacheMode) === 'aggressive' ? 'Standard' : 'Aggressive')
    @php($nextQueryStringPolicy = $this->queryStringPolicy() === 'include' ? 'Ignore query strings' : 'Include query strings')

    <style>
        .fp-setting-stack {
            display: grid;
            gap: 0;
        }

        .fp-setting-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: 1rem 0;
            border-top: 1px solid rgba(15, 23, 42, 0.08);
        }

        .dark .fp-setting-row {
            border-top-color: rgba(255, 255, 255, 0.08);
        }

        .fp-setting-row:first-child {
            padding-top: 0;
            border-top: 0;
        }

        .fp-setting-copy {
            display: grid;
            gap: 0.25rem;
            min-width: 0;
            flex: 1 1 auto;
        }

        .fp-setting-label {
            font-size: 0.95rem;
            font-weight: 600;
            color: rgb(15 23 42);
        }

        .dark .fp-setting-label {
            color: rgb(248 250 252);
        }

        .fp-setting-description {
            font-size: 0.875rem;
            line-height: 1.45;
            color: rgb(71 85 105);
        }

        .dark .fp-setting-description {
            color: rgb(148 163 184);
        }

        .fp-setting-action {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 0.75rem;
            flex: 0 0 auto;
            text-align: right;
        }

        .fp-setting-state {
            font-size: 0.8rem;
            font-weight: 600;
            color: rgb(100 116 139);
            white-space: nowrap;
        }

        .dark .fp-setting-state {
            color: rgb(148 163 184);
        }

        .fp-cache-layout {
            display: grid;
            gap: 1.5rem;
        }

        .fp-cache-column {
            display: grid;
            gap: 1.5rem;
            align-content: start;
        }

        @media (min-width: 1280px) {
            .fp-cache-layout {
                grid-template-columns: minmax(0, 1.45fr) minmax(320px, 0.9fr);
                align-items: start;
            }
        }

        @media (max-width: 768px) {
            .fp-setting-row {
                flex-direction: column;
                align-items: flex-start;
            }

            .fp-setting-action {
                width: 100%;
                justify-content: space-between;
            }
        }
    </style>

    <div class="fp-protection-shell">
        @if (! $this->site)
            @include('filament.app.pages.protection.empty-state')
        @else
            @include('filament.app.pages.protection.edge-routing-warning')
            @include('filament.app.pages.protection.technical-details')

            <div class="fp-cache-layout">
                <div class="fp-cache-column">
                    <x-filament.app.settings.card
                        title="Cache Control"
                        description="Live cache settings that FirePhage now applies directly across the edge."
                        icon="heroicon-o-circle-stack"
                        :status="$developmentMode ? 'Development Mode' : ($cacheEnabled ? $cacheMode : 'Caching Off')"
                        :status-color="$developmentMode ? 'warning' : ($cacheEnabled ? 'success' : 'gray')"
                    >
                        <div class="fp-setting-stack">
                            <div class="fp-setting-row">
                                <div class="fp-setting-copy">
                                    <div class="fp-setting-label">Caching</div>
                                    <div class="fp-setting-description">Turn edge caching on or off for this site. This is the main cache switch for protected delivery.</div>
                                </div>
                                <div class="fp-setting-action">
                                    <span class="fp-setting-state">{{ $cacheEnabled ? 'Enabled' : 'Disabled' }}</span>
                                    <x-filament::button
                                        color="{{ $cacheEnabled ? 'gray' : 'primary' }}"
                                        wire:click="toggleCacheEnabled"
                                        wire:loading.attr="disabled"
                                        wire:target="toggleCacheEnabled"
                                    >
                                        {{ $cacheEnabled ? 'Disable' : 'Enable' }}
                                    </x-filament::button>
                                </div>
                            </div>

                            <div class="fp-setting-row">
                                <div class="fp-setting-copy">
                                    <div class="fp-setting-label">Cache Mode</div>
                                    <div class="fp-setting-description">Choose how aggressively FirePhage should keep cached content at the edge before returning to origin.</div>
                                </div>
                                <div class="fp-setting-action">
                                    <span class="fp-setting-state">{{ $cacheMode }}</span>
                                    <x-filament::button
                                        color="gray"
                                        wire:click="toggleCacheMode"
                                        wire:loading.attr="disabled"
                                        wire:target="toggleCacheMode"
                                        :disabled="! $cacheEnabled || $developmentMode"
                                    >
                                        Switch to {{ $nextCacheMode }}
                                    </x-filament::button>
                                </div>
                            </div>

                            <div class="fp-setting-row">
                                <div class="fp-setting-copy">
                                    <div class="fp-setting-label">Browser Cache</div>
                                    <div class="fp-setting-description">Control how long visitor browsers keep assets locally before checking again with the edge.</div>
                                </div>
                                <div class="fp-setting-action">
                                    <span class="fp-setting-state">{{ $browserCacheLabel }}</span>
                                    <x-filament::dropdown placement="bottom-end" width="xs" :teleport="true">
                                        <x-slot name="trigger">
                                            <x-filament::button
                                                color="gray"
                                                :disabled="! $cacheEnabled || $developmentMode"
                                            >
                                                Change
                                            </x-filament::button>
                                        </x-slot>

                                        <x-filament::dropdown.list>
                                            <x-filament::dropdown.list.item tag="button" type="button" wire:click="setBrowserCacheTtl(-1)">
                                                Respect origin
                                            </x-filament::dropdown.list.item>
                                            <x-filament::dropdown.list.item tag="button" type="button" wire:click="setBrowserCacheTtl(0)">
                                                Disabled
                                            </x-filament::dropdown.list.item>
                                            <x-filament::dropdown.list.item tag="button" type="button" wire:click="setBrowserCacheTtl(300)">
                                                5 minutes
                                            </x-filament::dropdown.list.item>
                                            <x-filament::dropdown.list.item tag="button" type="button" wire:click="setBrowserCacheTtl(3600)">
                                                1 hour
                                            </x-filament::dropdown.list.item>
                                            <x-filament::dropdown.list.item tag="button" type="button" wire:click="setBrowserCacheTtl(14400)">
                                                4 hours
                                            </x-filament::dropdown.list.item>
                                            <x-filament::dropdown.list.item tag="button" type="button" wire:click="setBrowserCacheTtl(86400)">
                                                1 day
                                            </x-filament::dropdown.list.item>
                                            <x-filament::dropdown.list.item tag="button" type="button" wire:click="setBrowserCacheTtl(604800)">
                                                7 days
                                            </x-filament::dropdown.list.item>
                                        </x-filament::dropdown.list>
                                    </x-filament::dropdown>
                                </div>
                            </div>

                            <div class="fp-setting-row">
                                <div class="fp-setting-copy">
                                    <div class="fp-setting-label">Query Strings</div>
                                    <div class="fp-setting-description">Decide whether URLs with different query parameters should reuse the same cached response or be treated separately.</div>
                                </div>
                                <div class="fp-setting-action">
                                    <span class="fp-setting-state">{{ $queryStringLabel }}</span>
                                    <x-filament::button
                                        color="gray"
                                        wire:click="toggleQueryStringPolicy"
                                        wire:loading.attr="disabled"
                                        wire:target="toggleQueryStringPolicy"
                                        :disabled="! $cacheEnabled || $developmentMode"
                                    >
                                        {{ $nextQueryStringPolicy }}
                                    </x-filament::button>
                                </div>
                            </div>

                            <div class="fp-setting-row">
                                <div class="fp-setting-copy">
                                    <div class="fp-setting-label">Purge Cache</div>
                                    <div class="fp-setting-description">Flush cached content across the edge when you need content changes to appear immediately.</div>
                                </div>
                                <div class="fp-setting-action">
                                    <span class="fp-setting-state">{{ $this->lastAction(['edge.cache_purge', 'cloudfront.invalidate']) }}</span>
                                    <x-filament::button
                                        color="gray"
                                        wire:click="purgeCache"
                                        wire:loading.attr="disabled"
                                        wire:target="purgeCache"
                                        :disabled="$developmentMode"
                                    >
                                        Purge
                                    </x-filament::button>
                                </div>
                            </div>
                        </div>
                    </x-filament.app.settings.card>

                    <x-filament.app.settings.card
                        title="Optimization Controls"
                        description="These controls manage edge optimization behavior. Development mode temporarily bypasses them."
                        icon="heroicon-o-sparkles"
                        :status="collect([
                            $this->optimizerMinifyCssEnabled(),
                            $this->optimizerMinifyJsEnabled(),
                            $this->optimizerImagesEnabled(),
                        ])->filter()->count().' enabled'"
                        status-color="primary"
                    >
                        <div class="fp-setting-stack">
                            <div class="fp-setting-row">
                                <div class="fp-setting-copy">
                                    <div class="fp-setting-label">CSS Minification</div>
                                    <div class="fp-setting-description">Reduce CSS payload size at the edge to improve delivery without changing the origin files.</div>
                                </div>
                                <div class="fp-setting-action">
                                    <span class="fp-setting-state">{{ $this->optimizerMinifyCssEnabled() ? 'Enabled' : 'Disabled' }}</span>
                                    <x-filament::button
                                        color="{{ $this->optimizerMinifyCssEnabled() ? 'gray' : 'primary' }}"
                                        wire:click="toggleOptimizerMinifyCss"
                                        wire:loading.attr="disabled"
                                        wire:target="toggleOptimizerMinifyCss"
                                        :disabled="$developmentMode"
                                    >
                                        {{ $this->optimizerMinifyCssEnabled() ? 'Disable' : 'Enable' }}
                                    </x-filament::button>
                                </div>
                            </div>

                            <div class="fp-setting-row">
                                <div class="fp-setting-copy">
                                    <div class="fp-setting-label">JavaScript Minification</div>
                                    <div class="fp-setting-description">Compress JavaScript responses at the edge for faster transfer while keeping the same origin assets.</div>
                                </div>
                                <div class="fp-setting-action">
                                    <span class="fp-setting-state">{{ $this->optimizerMinifyJsEnabled() ? 'Enabled' : 'Disabled' }}</span>
                                    <x-filament::button
                                        color="{{ $this->optimizerMinifyJsEnabled() ? 'gray' : 'primary' }}"
                                        wire:click="toggleOptimizerMinifyJs"
                                        wire:loading.attr="disabled"
                                        wire:target="toggleOptimizerMinifyJs"
                                        :disabled="$developmentMode"
                                    >
                                        {{ $this->optimizerMinifyJsEnabled() ? 'Disable' : 'Enable' }}
                                    </x-filament::button>
                                </div>
                            </div>

                            <div class="fp-setting-row">
                                <div class="fp-setting-copy">
                                    <div class="fp-setting-label">Image Optimization</div>
                                    <div class="fp-setting-description">Let the edge optimize supported images automatically instead of always serving the raw origin files.</div>
                                </div>
                                <div class="fp-setting-action">
                                    <span class="fp-setting-state">{{ $this->optimizerImagesEnabled() ? 'Enabled' : 'Disabled' }}</span>
                                    <x-filament::button
                                        color="{{ $this->optimizerImagesEnabled() ? 'gray' : 'primary' }}"
                                        wire:click="toggleOptimizerImages"
                                        wire:loading.attr="disabled"
                                        wire:target="toggleOptimizerImages"
                                        :disabled="$developmentMode"
                                    >
                                        {{ $this->optimizerImagesEnabled() ? 'Disable' : 'Enable' }}
                                    </x-filament::button>
                                </div>
                            </div>
                        </div>
                    </x-filament.app.settings.card>

                    <x-filament.app.settings.card
                        title="WordPress Cache Bypass"
                        description="These path rules keep sensitive WordPress traffic uncached and unoptimized at the edge. Admin defaults seed new sites, but this site can change them here."
                        icon="heroicon-o-shield-exclamation"
                        :status="collect($cacheExclusions)->where('enabled', true)->count().' active'"
                        status-color="primary"
                    >
                        <div class="fi-ta-content-ctn overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-800">
                            <table class="fi-ta-table w-full text-sm">
                                <thead>
                                    <tr class="fi-ta-header-row">
                                        <th class="fi-ta-header-cell">Path</th>
                                        <th class="fi-ta-header-cell">Purpose</th>
                                        <th class="fi-ta-header-cell">State</th>
                                        <th class="fi-ta-header-cell fi-align-end">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($cacheExclusions as $rule)
                                        <tr class="fi-ta-row">
                                            <td class="fi-ta-cell font-mono">{{ $rule['path_pattern'] }}</td>
                                            <td class="fi-ta-cell">{{ $rule['reason'] !== '' ? $rule['reason'] : 'Managed WordPress bypass rule' }}</td>
                                            <td class="fi-ta-cell">
                                                <x-filament::badge :color="$rule['enabled'] ? 'success' : 'gray'">
                                                    {{ $rule['enabled'] ? 'Enabled' : 'Disabled' }}
                                                </x-filament::badge>
                                            </td>
                                            <td class="fi-ta-cell fi-align-end">
                                                <x-filament::button
                                                    size="sm"
                                                    color="{{ $rule['enabled'] ? 'gray' : 'primary' }}"
                                                    wire:click="toggleCacheExclusion('{{ $rule['path_pattern'] }}')"
                                                    wire:loading.attr="disabled"
                                                    wire:target="toggleCacheExclusion('{{ $rule['path_pattern'] }}')"
                                                >
                                                    {{ $rule['enabled'] ? 'Disable' : 'Enable' }}
                                                </x-filament::button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </x-filament.app.settings.card>

                    @if ($this->isSimpleMode())
                        <x-filament::section heading="Need More Cache Detail?" icon="heroicon-o-adjustments-horizontal">
                            <p class="text-sm">Simple mode keeps this focused. Switch to Pro for cache miss breakdown and purge history table.</p>
                            <x-slot name="footer">
                                <x-filament::actions alignment="end">
                                    <x-filament::button wire:click="switchToProMode" color="gray">Switch to Pro mode</x-filament::button>
                                </x-filament::actions>
                            </x-slot>
                        </x-filament::section>
                    @else
                        <x-filament.app.settings.card title="Cache Hit Ratio Trend" description="Daily cache efficiency over the recent period." icon="heroicon-o-arrow-trending-up">
                            @if (empty($trend))
                                <p class="text-sm opacity-75">No cache trend data yet.</p>
                            @else
                                <div class="grid gap-2">
                                    @foreach ($trend as $row)
                                        <div class="grid gap-1">
                                            <div class="flex items-center justify-between text-xs">
                                                <span>{{ $row['day'] }}</span>
                                                <span>{{ $row['hit'] }}%</span>
                                            </div>
                                            <div class="h-2 rounded-full bg-gray-100 dark:bg-gray-800">
                                                <div class="h-2 rounded-full bg-success-500" style="width: {{ $row['hit'] }}%"></div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </x-filament.app.settings.card>
                    @endif
                </div>

                <div class="fp-cache-column">
                    <x-filament.app.settings.card
                        title="Testing & Safety"
                        description="Use temporary bypass modes when you need to debug behavior without permanently changing your cache setup."
                        icon="heroicon-o-beaker"
                        :status="$troubleshootingMode ? 'Troubleshooting On' : ($developmentMode ? 'Development On' : 'Normal')"
                        :status-color="$troubleshootingMode ? 'danger' : ($developmentMode ? 'warning' : 'success')"
                    >
                        <div class="fp-setting-stack">
                            <div class="fp-setting-row">
                                <div class="fp-setting-copy">
                                    <div class="fp-setting-label">Development Mode</div>
                                    <div class="fp-setting-description">Temporarily bypass cache and optimizer behavior while you test fresh origin responses and content changes.</div>
                                </div>
                                <div class="fp-setting-action">
                                    <span class="fp-setting-state">{{ $developmentMode ? 'Enabled' : 'Disabled' }}</span>
                                    <x-filament::button
                                        color="{{ $developmentMode ? 'warning' : 'gray' }}"
                                        wire:click="toggleDevelopmentMode"
                                        wire:loading.attr="disabled"
                                        wire:target="toggleDevelopmentMode"
                                    >
                                        {{ $developmentMode ? 'Disable Development Mode' : 'Enable Development Mode' }}
                                    </x-filament::button>
                                </div>
                            </div>

                            <div class="fp-setting-row">
                                <div class="fp-setting-copy">
                                    <div class="fp-setting-label">Troubleshooting Mode</div>
                                    <div class="fp-setting-description">Use the broader debug mode when cache bypass alone is not enough and you need edge protection to stand down temporarily too.</div>
                                </div>
                                <div class="fp-setting-action">
                                    <span class="fp-setting-state">{{ $troubleshootingMode ? 'Enabled' : 'Disabled' }}</span>
                                    <x-filament::button
                                        color="{{ $troubleshootingMode ? 'danger' : 'gray' }}"
                                        wire:click="toggleTroubleshootingMode"
                                        wire:loading.attr="disabled"
                                        wire:target="toggleTroubleshootingMode"
                                    >
                                        {{ $troubleshootingMode ? 'Disable Troubleshooting Mode' : 'Enable Troubleshooting Mode' }}
                                    </x-filament::button>
                                </div>
                            </div>
                        </div>
                    </x-filament.app.settings.card>

                    <x-filament.app.settings.card
                        title="Purge Scope"
                        description="Purge everything or target a single path when you only need one URL family refreshed."
                        icon="heroicon-o-arrow-path"
                        :status="$this->purgePath !== '' ? 'Path Ready' : 'Full Site Purge'"
                        status-color="gray"
                    >
                        <div class="grid gap-3">
                            <label class="grid gap-2">
                                <span class="text-sm font-medium text-gray-900 dark:text-gray-100">Specific path</span>
                                <input
                                    type="text"
                                    wire:model.live.defer="purgePath"
                                    placeholder="/wp-content/uploads/*"
                                    class="fi-input block w-full rounded-xl border-none bg-white/70 px-3 py-2 text-sm text-gray-950 shadow-sm ring-1 ring-gray-950/10 outline-none transition focus:ring-2 focus:ring-primary-500 dark:bg-white/5 dark:text-white dark:ring-white/10"
                                >
                            </label>
                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                Use a path like <code>/about-us</code> or a wildcard like <code>/wp-content/uploads/*</code>.
                            </p>
                        </div>

                        <x-slot name="footer">
                            <x-filament::actions alignment="end">
                                <x-filament::button
                                    color="gray"
                                    wire:click="purgeCachePath"
                                    wire:loading.attr="disabled"
                                    wire:target="purgeCachePath"
                                >
                                    Purge Path
                                </x-filament::button>
                            </x-filament::actions>
                        </x-slot>
                    </x-filament.app.settings.card>

                    @unless ($this->isSimpleMode())
                        <x-filament.app.settings.card title="Top Cache Misses" description="Paths with highest miss or error activity." icon="heroicon-o-exclamation-triangle">
                            @if (empty($misses))
                                <p class="text-sm opacity-75">No cache miss telemetry yet.</p>
                            @else
                                <div class="fi-ta-content-ctn overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-800">
                                    <table class="fi-ta-table w-full text-sm">
                                        <thead>
                                            <tr class="fi-ta-header-row">
                                                <th class="fi-ta-header-cell">Path</th>
                                                <th class="fi-ta-header-cell fi-align-end">Misses</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($misses as $row)
                                                <tr class="fi-ta-row">
                                                    <td class="fi-ta-cell">{{ $row['path'] }}</td>
                                                    <td class="fi-ta-cell fi-align-end">{{ number_format((int) $row['misses']) }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        </x-filament.app.settings.card>

                        <x-filament.app.settings.card title="Purge History" description="Recent edge cache purge requests." icon="heroicon-o-clock">
                            @if (empty($history))
                                <p class="text-sm opacity-75">No purge history yet.</p>
                            @else
                                <div class="fi-ta-content-ctn overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-800">
                                    <table class="fi-ta-table w-full text-sm">
                                        <thead>
                                            <tr class="fi-ta-header-row">
                                                <th class="fi-ta-header-cell">Timestamp</th>
                                                <th class="fi-ta-header-cell">Result</th>
                                                <th class="fi-ta-header-cell">Purge ID</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($history as $row)
                                                <tr class="fi-ta-row">
                                                    <td class="fi-ta-cell">{{ $row['timestamp']?->diffForHumans() ?? 'n/a' }}</td>
                                                    <td class="fi-ta-cell">{{ ucfirst((string) $row['status']) }}</td>
                                                    <td class="fi-ta-cell">{{ $row['purge_id'] }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        </x-filament.app.settings.card>
                    @endunless
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>
