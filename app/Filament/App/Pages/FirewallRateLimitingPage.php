<?php

namespace App\Filament\App\Pages;

use App\Models\Site;
use App\Models\EdgeRequestLog;
use App\Filament\App\Pages\SupportPage;
use App\Services\Bunny\BunnyGlobalDefaultsService;
use App\Services\Bunny\BunnyShieldSecurityService;
use App\Services\SiteContext;
use App\Services\UiModeManager;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Illuminate\Http\Request;
use Throwable;

class FirewallRateLimitingPage extends BaseProtectionPage implements HasActions, HasForms
{
    use InteractsWithActions;
    use InteractsWithForms;

    protected static string|\UnitEnum|null $navigationGroup = 'Security & Protection';

    protected static ?string $slug = 'firewall-rate-limiting';

    protected static ?int $navigationSort = -37;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clock';

    protected static ?string $navigationLabel = 'Rate Limiting';

    protected static ?string $title = 'Rate Limiting';

    protected string $view = 'filament.app.pages.protection.firewall-rate-limiting';

    public ?array $data = [];

    public ?string $editingRateLimitId = null;

    /** @var array<int, array<string, mixed>> */
    public array $rateLimits = [];

    public function mount(Request $request, SiteContext $siteContext, UiModeManager $uiMode): void
    {
        parent::mount($request, $siteContext, $uiMode);

        $this->reloadState();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Select::make('action')
                    ->label('Action')
                    ->options([
                        'block' => 'Block',
                        'challenge' => 'Challenge',
                    ])
                    ->default('challenge')
                    ->required(),
                Select::make('window_seconds')
                    ->label('Window')
                    ->options([
                        1 => '1 second',
                        10 => '10 seconds',
                        60 => '60 seconds',
                        300 => '5 minutes',
                    ])
                    ->default(10)
                    ->required(),
                Select::make('requests')
                    ->label('Requests per window')
                    ->options([
                        20 => '20',
                        50 => '50',
                        100 => '100',
                        250 => '250',
                        500 => '500',
                        1000 => '1000',
                    ])
                    ->default(100)
                    ->required(),
                Textarea::make('name')
                    ->label('Rule name')
                    ->rows(2)
                    ->required(),
                Textarea::make('description')
                    ->label('Description')
                    ->rows(2),
                Textarea::make('path_pattern')
                    ->label('Path pattern (optional)')
                    ->rows(2)
                    ->placeholder('/login*'),
            ])
            ->columns(2)
            ->statePath('data');
    }

    public function createRateLimit(): void
    {
        if (! $this->site || $this->site->provider !== Site::PROVIDER_BUNNY) {
            Notification::make()->title('Rate limiting is available only for standard edge sites.')->warning()->send();

            return;
        }

        if (! $this->ensureNotDemoReadOnly('Rate limit changes')) {
            return;
        }

        $state = $this->form->getState();
        $conflict = $this->conflictingManagedRule($this->form->getState());

        if ($conflict !== null) {
            Notification::make()
                ->title('This rule collides with a platform-managed protection rule.')
                ->body("The path pattern {$conflict['path_pattern']} is already protected by FirePhage system defaults. Contact support if you need this adjusted: ".SupportPage::getUrl())
                ->danger()
                ->persistent()
                ->send();

            return;
        }

        try {
            $service = app(BunnyShieldSecurityService::class);

            if ($this->editingRateLimitId !== null) {
                $current = collect($this->rateLimits)->firstWhere('id', $this->editingRateLimitId);

                if (is_array($current) && $current !== []) {
                    if ((bool) ($current['is_live'] ?? false) && (string) ($current['live_id'] ?? '') !== '') {
                        $service->updateRateLimit($this->site, (string) $current['live_id'], $state);
                    } else {
                        $service->updateSavedDisabledRateLimit($this->site, $this->editingRateLimitId, $state);
                    }
                }

                $this->editingRateLimitId = null;
                $message = 'Rate limit rule updated.';
            } else {
                $service->createRateLimit($this->site, $state);
                $message = 'Rate limit rule created.';
            }

            $this->reloadState();
            Notification::make()->title($message)->success()->send();
        } catch (Throwable $e) {
            report($e);
            Notification::make()->title('Rate limit save failed.')->body($e->getMessage())->danger()->send();
        }
    }

    public function editRateLimit(string $id): void
    {
        $rule = collect($this->rateLimits)->firstWhere('id', $id);

        if (! is_array($rule) || $rule === []) {
            Notification::make()->title('Unable to find that rate limit rule.')->warning()->send();

            return;
        }

        $this->editingRateLimitId = $id;
        $this->form->fill([
            'action' => (string) ($rule['action'] ?? 'block'),
            'window_seconds' => (int) ($rule['window_seconds'] ?? 10),
            'requests' => (int) ($rule['requests'] ?? 100),
            'name' => (string) ($rule['name'] ?? 'Rate limit'),
            'description' => (string) ($rule['description'] ?? ''),
            'path_pattern' => (string) ($rule['path_pattern'] ?? ''),
        ]);
    }

    public function applyPreset(string $preset): void
    {
        $payload = match ($preset) {
            'login' => [
                'action' => 'challenge',
                'window_seconds' => 10,
                'requests' => 20,
                'name' => 'Login burst protection',
                'description' => 'Slow down repeated login attempts before they reach the application.',
                'path_pattern' => '/login*',
            ],
            'api' => [
                'action' => 'block',
                'window_seconds' => 10,
                'requests' => 250,
                'name' => 'API burst control',
                'description' => 'Clamp sudden API floods without affecting the rest of the site.',
                'path_pattern' => '/api/*',
            ],
            'search' => [
                'action' => 'challenge',
                'window_seconds' => 10,
                'requests' => 50,
                'name' => 'Search abuse damping',
                'description' => 'Reduce automated search abuse and scrape spikes.',
                'path_pattern' => '/search*',
            ],
            default => null,
        };

        if ($payload === null) {
            return;
        }

        $this->editingRateLimitId = null;
        $this->form->fill($payload);
    }

    public function cancelRateLimitEdit(): void
    {
        $this->editingRateLimitId = null;
        $this->reloadState();
    }

    public function toggleRateLimit(string $id): void
    {
        if (! $this->site || $this->site->provider !== Site::PROVIDER_BUNNY) {
            return;
        }

        if (! $this->ensureNotDemoReadOnly('Rate limit changes')) {
            return;
        }

        $rule = collect($this->rateLimits)->firstWhere('id', $id);

        if (! is_array($rule) || $rule === []) {
            Notification::make()->title('Unable to find that rate limit rule.')->warning()->send();

            return;
        }

        try {
            $service = app(BunnyShieldSecurityService::class);

            if ((bool) ($rule['enabled'] ?? false)) {
                $service->disableRateLimit($this->site, $id);
                $message = 'Rate limit disabled.';
            } else {
                $service->enableRateLimit($this->site, $id);
                $message = 'Rate limit enabled.';
            }

            $this->reloadState();
            Notification::make()->title($message)->success()->send();
        } catch (Throwable $e) {
            report($e);
            Notification::make()->title('Unable to update the rate limit rule.')->body($e->getMessage())->danger()->send();
        }
    }

    public function deleteRateLimitRule(string $id): void
    {
        if (! $this->site || $this->site->provider !== Site::PROVIDER_BUNNY) {
            return;
        }

        if (! $this->ensureNotDemoReadOnly('Rate limit changes')) {
            return;
        }

        $rule = collect($this->rateLimits)->firstWhere('id', $id);

        if (! is_array($rule) || $rule === []) {
            Notification::make()->title('Unable to find that rate limit rule.')->warning()->send();

            return;
        }

        try {
            $service = app(BunnyShieldSecurityService::class);

            if ((bool) ($rule['is_live'] ?? false) && (string) ($rule['live_id'] ?? '') !== '') {
                $service->deleteRateLimit($this->site, (string) $rule['live_id']);
            } else {
                $service->deleteSavedDisabledRateLimit($this->site, $id);
            }

            $this->reloadState();
            Notification::make()->title('Rate limit deleted.')->success()->send();
        } catch (Throwable $e) {
            report($e);
            Notification::make()->title('Unable to delete the rate limit rule.')->body($e->getMessage())->danger()->send();
        }
    }

    protected function reloadState(): void
    {
        $this->rateLimits = [];
        $this->editingRateLimitId = null;

        $this->form->fill([
            'action' => 'block',
            'window_seconds' => 10,
            'requests' => 100,
            'name' => 'Default rate limit',
            'description' => null,
            'path_pattern' => null,
        ]);

        if (! $this->site || $this->site->provider !== Site::PROVIDER_BUNNY) {
            return;
        }

        try {
            $this->rateLimits = app(BunnyShieldSecurityService::class)->presentRateLimits($this->site);
        } catch (Throwable $e) {
            report($e);
            Notification::make()->title('Unable to load rate limit rules.')->body($e->getMessage())->warning()->send();
        }
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>|null
     */
    protected function conflictingManagedRule(array $state): ?array
    {
        $requested = trim(strtolower((string) ($state['path_pattern'] ?? '')));

        if ($requested === '') {
            return null;
        }

        foreach (app(BunnyGlobalDefaultsService::class)->activeSecurityRateLimits() as $rule) {
            $managedPattern = trim(strtolower((string) ($rule['path_pattern'] ?? '')));

            if ($managedPattern === '') {
                continue;
            }

            if ($requested === $managedPattern || str_contains($requested, $managedPattern) || str_contains($managedPattern, $requested)) {
                return $rule;
            }
        }

        return null;
    }

    /**
     * @return array<int, array<string, string>>
     */
    public function rateLimitRecommendations(): array
    {
        return [
            [
                'name' => 'Protect high-risk endpoints',
                'description' => 'Use path-based challenge or block rules for login, search, or API routes that attract abuse.',
            ],
            [
                'name' => 'Prefer targeted rules',
                'description' => 'Apply limits to the specific path that is getting abused instead of throttling the whole site.',
            ],
            [
                'name' => 'Leave managed defaults alone',
                'description' => 'Core WordPress-sensitive paths already have FirePhage-managed protection. Contact support if you need them changed.',
            ],
        ];
    }

    /**
     * @return array<int, array{id:string,name:string,description:string}>
     */
    public function rateLimitPresets(): array
    {
        return [
            [
                'id' => 'login',
                'name' => 'Login protection',
                'description' => 'Good for authentication or session endpoints that attract bursts.',
            ],
            [
                'id' => 'api',
                'name' => 'API burst control',
                'description' => 'Useful when machine traffic can spike much faster than human visitors.',
            ],
            [
                'id' => 'search',
                'name' => 'Search abuse damping',
                'description' => 'Useful for scrape-prone search or filter endpoints.',
            ],
        ];
    }

    public function rateLimitActionLabel(array $rule): string
    {
        return ucfirst((string) ($rule['action'] ?? 'block'));
    }

    public function rateLimitActionColor(array $rule): string
    {
        return match ((string) ($rule['action'] ?? 'block')) {
            'allow' => 'success',
            'challenge' => 'warning',
            default => 'danger',
        };
    }

    public function rateLimitPathLabel(array $rule): string
    {
        $path = trim((string) ($rule['path_pattern'] ?? ''));

        return $path !== '' ? $path : 'All paths';
    }

    public function rateLimitStateColor(array $rule): string
    {
        return (bool) ($rule['enabled'] ?? false) ? 'success' : 'gray';
    }

    public function rateLimitEstimatedMatches(array $rule): string
    {
        if (! $this->site) {
            return 'No telemetry';
        }

        $pattern = trim((string) ($rule['path_pattern'] ?? ''));
        $query = EdgeRequestLog::query()
            ->where('site_id', $this->site->id)
            ->where('event_at', '>=', now()->subDay());

        if ($pattern !== '') {
            $like = str_replace('*', '%', $pattern);
            $query->where('path', 'like', $like);
        }

        return number_format((int) $query->count()).' requests in 24h';
    }

    public function rateLimitTelemetryLabel(array $rule): string
    {
        return trim((string) ($rule['path_pattern'] ?? '')) !== ''
            ? 'Path activity'
            : 'Site-wide activity';
    }

    public function editingRateLimitName(): ?string
    {
        if ($this->editingRateLimitId === null) {
            return null;
        }

        $rule = collect($this->rateLimits)->firstWhere('id', $this->editingRateLimitId);

        return is_array($rule) ? ($rule['name'] ?? null) : null;
    }

    public function deleteRateLimitAction(): Action
    {
        return Action::make('deleteRateLimit')
            ->requiresConfirmation()
            ->color('danger')
            ->modalIcon('heroicon-m-trash')
            ->modalHeading('Delete rate limit rule?')
            ->modalDescription('This removes the rule from FirePhage. If it is enabled, live enforcement will be removed as well.')
            ->modalSubmitActionLabel('Delete rule')
            ->action(function (array $arguments): void {
                $id = (string) ($arguments['id'] ?? '');

                if ($id === '') {
                    Notification::make()->title('Unable to find that rate limit rule.')->warning()->send();

                    return;
                }

                $this->deleteRateLimitRule($id);
            });
    }
}
