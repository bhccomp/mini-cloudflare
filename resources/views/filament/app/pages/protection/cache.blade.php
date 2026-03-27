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

    <div class="fp-protection-shell">
        @if (! $this->site)
            @include('filament.app.pages.protection.empty-state')
        @else
            @include('filament.app.pages.protection.edge-routing-warning')
            @include('filament.app.pages.protection.technical-details')

            <div class="fp-protection-grid">
                <x-filament.app.settings.card
                    title="Cache Control"
                    description="Live cache settings that FirePhage now applies directly to the Bunny edge."
                    icon="heroicon-o-circle-stack"
                    :status="$developmentMode ? 'Development Mode' : ($cacheEnabled ? $cacheMode : 'Caching Off')"
                    :status-color="$developmentMode ? 'warning' : ($cacheEnabled ? 'success' : 'gray')"
                >
                    <x-filament.app.settings.key-value-grid :rows="[
                        ['label' => 'Caching', 'value' => $cacheEnabled ? 'Enabled' : 'Disabled'],
                        ['label' => 'Mode', 'value' => $cacheMode],
                        ['label' => 'Browser Cache', 'value' => $browserCacheLabel],
                        ['label' => 'Query Strings', 'value' => $queryStringLabel],
                        ['label' => 'Cache Hit Ratio', 'value' => $this->metricCacheHitRatio()],
                        ['label' => 'Last Cache Change', 'value' => $this->lastAction(['site.control.cache_enabled', 'site.control.cache_mode'])],
                        ['label' => 'Last Sync', 'value' => $this->site->syncFreshnessForHumans('Not synced yet')],
                    ]" />

                    <x-slot name="footer">
                        <x-filament::actions alignment="end">
                            <x-filament::button
                                color="{{ $cacheEnabled ? 'gray' : 'primary' }}"
                                wire:click="toggleCacheEnabled"
                                wire:loading.attr="disabled"
                                wire:target="toggleCacheEnabled"
                            >
                                {{ $cacheEnabled ? 'Disable Cache' : 'Enable Cache' }}
                            </x-filament::button>
                            <x-filament::button
                                color="gray"
                                wire:click="toggleCacheMode"
                                wire:loading.attr="disabled"
                                wire:target="toggleCacheMode"
                                :disabled="! $cacheEnabled || $developmentMode"
                            >
                                Switch to {{ strtolower($cacheMode) === 'aggressive' ? 'Standard' : 'Aggressive' }}
                            </x-filament::button>
                            <x-filament::button
                                color="gray"
                                wire:click="cycleBrowserCacheTtl"
                                wire:loading.attr="disabled"
                                wire:target="cycleBrowserCacheTtl"
                                :disabled="! $cacheEnabled || $developmentMode"
                            >
                                Browser Cache: {{ $browserCacheLabel }}
                            </x-filament::button>
                            <x-filament::button
                                color="gray"
                                wire:click="toggleQueryStringPolicy"
                                wire:loading.attr="disabled"
                                wire:target="toggleQueryStringPolicy"
                                :disabled="! $cacheEnabled || $developmentMode"
                            >
                                {{ $queryStringLabel }}
                            </x-filament::button>
                            <x-filament::button
                                color="gray"
                                wire:click="purgeCache"
                                wire:loading.attr="disabled"
                                wire:target="purgeCache"
                                :disabled="$developmentMode"
                            >
                                Purge Cache
                            </x-filament::button>
                        </x-filament::actions>
                    </x-slot>
                </x-filament.app.settings.card>

                <x-filament.app.settings.card
                    title="Testing & Safety"
                    description="Use temporary bypass modes when you need to debug behavior without permanently changing your cache setup."
                    icon="heroicon-o-beaker"
                    :status="$troubleshootingMode ? 'Troubleshooting On' : ($developmentMode ? 'Development On' : 'Normal')"
                    :status-color="$troubleshootingMode ? 'danger' : ($developmentMode ? 'warning' : 'success')"
                >
                    <x-filament.app.settings.key-value-grid :rows="[
                        ['label' => 'Development Mode', 'value' => $developmentMode ? 'Enabled (cache + optimizers bypassed)' : 'Disabled'],
                        ['label' => 'Troubleshooting Mode', 'value' => $troubleshootingMode ? 'Enabled (cache bypass + edge protection relaxed)' : 'Disabled'],
                        ['label' => 'Development Mode Does', 'value' => 'Bypasses cache and optimization only'],
                        ['label' => 'Troubleshooting Does', 'value' => 'Bypasses cache and relaxes edge protection for deeper debugging'],
                        ['label' => 'Recent Purge', 'value' => $this->lastAction(['edge.cache_purge', 'cloudfront.invalidate'])],
                        ['label' => 'Recent Mode Change', 'value' => $this->lastAction(['edge.development_mode', 'edge.troubleshooting_mode'])],
                    ]" />

                    <x-slot name="footer">
                        <x-filament::actions alignment="end">
                            <x-filament::button
                                color="{{ $developmentMode ? 'warning' : 'gray' }}"
                                wire:click="toggleDevelopmentMode"
                                wire:loading.attr="disabled"
                                wire:target="toggleDevelopmentMode"
                            >
                                {{ $developmentMode ? 'Disable Development Mode' : 'Enable Development Mode' }}
                            </x-filament::button>
                            <x-filament::button
                                color="{{ $troubleshootingMode ? 'danger' : 'gray' }}"
                                wire:click="toggleTroubleshootingMode"
                                wire:loading.attr="disabled"
                                wire:target="toggleTroubleshootingMode"
                            >
                                {{ $troubleshootingMode ? 'Disable Troubleshooting' : 'Enable Troubleshooting' }}
                            </x-filament::button>
                        </x-filament::actions>
                    </x-slot>
                </x-filament.app.settings.card>
            </div>

            <div class="fp-protection-grid">
                <x-filament.app.settings.card
                    title="Optimization Controls"
                    description="These controls map to Bunny optimizer settings. Development mode temporarily bypasses them."
                    icon="heroicon-o-sparkles"
                    :status="collect([
                        $this->optimizerMinifyCssEnabled(),
                        $this->optimizerMinifyJsEnabled(),
                        $this->optimizerImagesEnabled(),
                    ])->filter()->count().' enabled'"
                    status-color="primary"
                >
                    <x-filament.app.settings.key-value-grid :rows="[
                        ['label' => 'CSS Minification', 'value' => $this->optimizerMinifyCssEnabled() ? 'Enabled' : 'Disabled'],
                        ['label' => 'JavaScript Minification', 'value' => $this->optimizerMinifyJsEnabled() ? 'Enabled' : 'Disabled'],
                        ['label' => 'Image Optimization', 'value' => $this->optimizerImagesEnabled() ? 'Enabled' : 'Disabled'],
                        ['label' => 'Recent Optimizer Change', 'value' => $this->lastAction(['site.control.optimizer_minify_css', 'site.control.optimizer_minify_js', 'site.control.optimizer_images'])],
                    ]" />

                    <x-slot name="footer">
                        <x-filament::actions alignment="end">
                            <x-filament::button
                                color="{{ $this->optimizerMinifyCssEnabled() ? 'gray' : 'primary' }}"
                                wire:click="toggleOptimizerMinifyCss"
                                wire:loading.attr="disabled"
                                wire:target="toggleOptimizerMinifyCss"
                                :disabled="$developmentMode"
                            >
                                {{ $this->optimizerMinifyCssEnabled() ? 'Disable CSS Minify' : 'Enable CSS Minify' }}
                            </x-filament::button>
                            <x-filament::button
                                color="{{ $this->optimizerMinifyJsEnabled() ? 'gray' : 'primary' }}"
                                wire:click="toggleOptimizerMinifyJs"
                                wire:loading.attr="disabled"
                                wire:target="toggleOptimizerMinifyJs"
                                :disabled="$developmentMode"
                            >
                                {{ $this->optimizerMinifyJsEnabled() ? 'Disable JS Minify' : 'Enable JS Minify' }}
                            </x-filament::button>
                            <x-filament::button
                                color="{{ $this->optimizerImagesEnabled() ? 'gray' : 'primary' }}"
                                wire:click="toggleOptimizerImages"
                                wire:loading.attr="disabled"
                                wire:target="toggleOptimizerImages"
                                :disabled="$developmentMode"
                            >
                                {{ $this->optimizerImagesEnabled() ? 'Disable Image Opt.' : 'Enable Image Opt.' }}
                            </x-filament::button>
                        </x-filament::actions>
                    </x-slot>
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
            </div>

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
            <div class="fp-protection-grid">
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
            </div>

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
            @endif
        @endif
    </div>
</x-filament-panels::page>
