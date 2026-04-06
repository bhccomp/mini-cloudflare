<?php

namespace App\Filament\Admin\Pages;

use App\Services\Bunny\BunnyGlobalDefaultsService;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class EdgeDefaultsPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-adjustments-horizontal';

    protected static string|\UnitEnum|null $navigationGroup = 'System';

    protected static ?string $navigationLabel = 'Edge Defaults';

    protected static ?string $title = 'Edge Defaults';

    protected static ?int $navigationSort = 20;

    protected string $view = 'filament.admin.pages.edge-defaults';

    public ?array $data = [];

    protected function getForms(): array
    {
        return [
            'form',
        ];
    }

    public function mount(): void
    {
        $defaults = app(BunnyGlobalDefaultsService::class)->defaults();

        $this->form->fill([
            'cache_exclusions' => $defaults['cache_exclusions'],
            'security_rate_limits' => $defaults['security_rate_limits'],
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Global Cache Exclusions')
                    ->description('Admin-only default templates for paths that should bypass cache and optimization on new Bunny-backed sites. Site-level cache settings can override these defaults later.')
                    ->schema([
                        Repeater::make('cache_exclusions')
                            ->addActionLabel('Add exclusion')
                            ->defaultItems(0)
                            ->schema([
                                TextInput::make('path_pattern')
                                    ->label('Path pattern')
                                    ->placeholder('/wp-admin/*')
                                    ->required(),
                                Textarea::make('reason')
                                    ->label('Reason')
                                    ->rows(2),
                                Toggle::make('enabled')
                                    ->label('Enabled')
                                    ->default(true),
                            ])
                            ->columns(3),
                    ]),
                Section::make('Global Security Rate Limits')
                    ->description('Hidden WordPress-style protection rules that FirePhage applies automatically to Bunny-backed sites.')
                    ->schema([
                        Repeater::make('security_rate_limits')
                            ->addActionLabel('Add security default')
                            ->defaultItems(0)
                            ->schema([
                                TextInput::make('slug')
                                    ->label('Key')
                                    ->helperText('Stable internal key used for syncing hidden defaults.')
                                    ->required(),
                                TextInput::make('name')
                                    ->label('Name')
                                    ->required(),
                                Textarea::make('description')
                                    ->label('Description')
                                    ->rows(2),
                                Select::make('action')
                                    ->label('Action')
                                    ->options([
                                        'block' => 'Block',
                                        'challenge' => 'Challenge',
                                        'allow' => 'Allow',
                                    ])
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
                                    ->required(),
                                Select::make('requests')
                                    ->label('Requests')
                                    ->options([
                                        5 => '5',
                                        10 => '10',
                                        15 => '15',
                                        20 => '20',
                                        50 => '50',
                                        100 => '100',
                                        120 => '120',
                                        250 => '250',
                                    ])
                                    ->required(),
                                TextInput::make('path_pattern')
                                    ->label('Path pattern')
                                    ->placeholder('/wp-login.php*'),
                                Toggle::make('enabled')
                                    ->label('Enabled')
                                    ->default(true),
                            ])
                            ->columns(4),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        app(BunnyGlobalDefaultsService::class)->save($this->form->getState());

        Notification::make()
            ->title('Edge defaults saved.')
            ->success()
            ->send();
    }

    public function applyToExistingSites(): void
    {
        $service = app(BunnyGlobalDefaultsService::class);
        $sites = $service->bunnySites();
        $applied = 0;

        foreach ($sites as $site) {
            try {
                $service->syncSecurityDefaults($site);
                $service->mergeMissingCacheExclusionsForSite($site);

                $applied++;
            } catch (\Throwable $e) {
                report($e);
            }
        }

        Notification::make()
            ->title("Applied default security and cache templates to {$applied} Bunny site(s).")
            ->success()
            ->send();
    }
}
