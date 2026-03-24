<?php

namespace App\Filament\App\Pages;

use App\Models\Site;
use App\Filament\App\Pages\SupportPage;
use App\Services\Bunny\BunnyGlobalDefaultsService;
use App\Services\Bunny\BunnyShieldSecurityService;
use App\Services\SiteContext;
use App\Services\UiModeManager;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Illuminate\Http\Request;
use Throwable;

class FirewallRateLimitingPage extends BaseProtectionPage implements HasForms
{
    use InteractsWithForms;

    protected static string|\UnitEnum|null $navigationGroup = 'Security & Protection';

    protected static ?string $slug = 'firewall-rate-limiting';

    protected static ?int $navigationSort = -37;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clock';

    protected static ?string $navigationLabel = 'Rate Limiting';

    protected static ?string $title = 'Rate Limiting';

    protected string $view = 'filament.app.pages.protection.firewall-rate-limiting';

    public ?array $data = [];

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
                        'allow' => 'Allow',
                    ])
                    ->default('block')
                    ->required(),
                Select::make('window_seconds')
                    ->label('Window')
                    ->options([
                        1 => '1 second',
                        5 => '5 seconds',
                        10 => '10 seconds',
                        30 => '30 seconds',
                        60 => '60 seconds',
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
            app(BunnyShieldSecurityService::class)->createRateLimit($this->site, $this->form->getState());
            $this->reloadState();

            Notification::make()->title('Rate limit rule created.')->success()->send();
        } catch (Throwable $e) {
            report($e);
            Notification::make()->title('Rate limit creation failed.')->body($e->getMessage())->danger()->send();
        }
    }

    protected function reloadState(): void
    {
        $this->rateLimits = [];

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
            $this->rateLimits = app(BunnyGlobalDefaultsService::class)->filterVisibleRateLimits(
                app(BunnyShieldSecurityService::class)->listRateLimits($this->site)
            );
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

}
