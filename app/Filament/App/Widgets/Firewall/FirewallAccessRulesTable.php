<?php

namespace App\Filament\App\Widgets\Firewall;

use App\Filament\App\Widgets\Concerns\ResolvesSelectedSite;
use App\Models\SiteFirewallRule;
use App\Services\Firewall\FirewallAccessControlService;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Carbon;
use Livewire\Attributes\On;

class FirewallAccessRulesTable extends TableWidget
{
    use ResolvesSelectedSite;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Configured Access Rules';

    #[On('firewall-access-rules-updated')]
    public function refreshRulesTable(): void
    {
        // Listener triggers component refresh.
    }

    public function table(Table $table): Table
    {
        return $table
            ->description('Staged and enforced rules with expiration and provider status.')
            ->query($this->query())
            ->defaultSort('id', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->since(),
                Tables\Columns\TextColumn::make('rule_type')
                    ->label('Type')
                    ->badge(),
                Tables\Columns\TextColumn::make('target')
                    ->label('Target')
                    ->formatStateUsing(function (string $state, SiteFirewallRule $record): string {
                        $count = (int) data_get($record->meta, 'entry_count', 0);
                        $isLegacySetLabel = preg_match('/\bset\s*\(\d+\)\b/i', $state) === 1;

                        if ($count > 1 || $isLegacySetLabel) {
                            $typeLabel = match ($record->rule_type) {
                                SiteFirewallRule::TYPE_COUNTRY => 'Country',
                                SiteFirewallRule::TYPE_CONTINENT => 'Continent',
                                SiteFirewallRule::TYPE_IP => 'IP',
                                SiteFirewallRule::TYPE_CIDR => 'CIDR',
                                default => 'Access',
                            };

                            $suffix = match (strtolower($record->action)) {
                                SiteFirewallRule::ACTION_ALLOW => 'Allowlist',
                                SiteFirewallRule::ACTION_CHALLENGE => 'Challenges',
                                default => 'Blocks',
                            };

                            return "{$typeLabel} {$suffix}";
                        }

                        return $state;
                    })
                    ->copyable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('meta.entry_count')
                    ->label('Entries')
                    ->formatStateUsing(fn ($state): string => $state ? (string) $state : '1')
                    ->badge()
                    ->color('gray'),
                Tables\Columns\TextColumn::make('action')
                    ->label('Action')
                    ->badge()
                    ->color(fn (string $state): string => match (strtolower($state)) {
                        'allow' => 'success',
                        'challenge' => 'warning',
                        default => 'danger',
                    }),
                Tables\Columns\TextColumn::make('mode')
                    ->label('Mode')
                    ->badge()
                    ->color(fn (string $state): string => $state === SiteFirewallRule::MODE_STAGED ? 'warning' : 'info'),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        SiteFirewallRule::STATUS_ACTIVE => 'success',
                        SiteFirewallRule::STATUS_REMOVED => 'gray',
                        SiteFirewallRule::STATUS_EXPIRED => 'gray',
                        SiteFirewallRule::STATUS_FAILED => 'danger',
                        default => 'warning',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        SiteFirewallRule::STATUS_REMOVED => 'Disabled',
                        default => str($state)->headline()->toString(),
                    }),
                Tables\Columns\TextColumn::make('meta.error')
                    ->label('Failure reason')
                    ->toggleable()
                    ->wrap()
                    ->limit(120),
                Tables\Columns\TextColumn::make('expires_at')
                    ->label('Expires')
                    ->formatStateUsing(fn ($state): string => $state ? $state->diffForHumans() : 'Never'),
                Tables\Columns\TextColumn::make('provider_rule_id')
                    ->label('Provider ID')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        SiteFirewallRule::STATUS_PENDING => 'Pending',
                        SiteFirewallRule::STATUS_ACTIVE => 'Active',
                        SiteFirewallRule::STATUS_REMOVED => 'Disabled',
                        SiteFirewallRule::STATUS_FAILED => 'Failed',
                        SiteFirewallRule::STATUS_EXPIRED => 'Expired',
                    ]),
                SelectFilter::make('mode')
                    ->options([
                        SiteFirewallRule::MODE_STAGED => 'Staged',
                        SiteFirewallRule::MODE_ENFORCED => 'Enforced',
                    ]),
                SelectFilter::make('action')
                    ->options(SiteFirewallRule::actionOptions()),
            ])
            ->recordActions([
                Action::make('edit')
                    ->label('Edit')
                    ->icon('heroicon-m-pencil-square')
                    ->fillForm(function (SiteFirewallRule $record): array {
                        $countryCodes = collect((array) data_get($record->meta, 'targets', []))
                            ->map(fn (mixed $value): string => strtoupper(trim((string) $value)))
                            ->filter(fn (string $code): bool => preg_match('/^[A-Z]{2}$/', $code) === 1)
                            ->values()
                            ->all();

                        if ($countryCodes === []) {
                            $countryCodes = collect(preg_split('/\r\n|\r|\n/', (string) data_get($record->meta, 'content', '')) ?: [])
                                ->map(fn (string $line): string => strtoupper(trim($line)))
                                ->filter(fn (string $code): bool => preg_match('/^[A-Z]{2}$/', $code) === 1)
                                ->values()
                                ->all();
                        }

                        return [
                            'rule_type' => $record->rule_type,
                            'target' => $record->target,
                            'country_codes' => $countryCodes,
                            'action' => $record->action,
                            'mode' => $record->mode,
                            'note' => $record->note,
                            'expires_at' => $record->expires_at,
                        ];
                    })
                    ->form(function (SiteFirewallRule $record): array {
                        return [
                            Select::make('rule_type')
                                ->label('Type')
                                ->options(SiteFirewallRule::typeOptions())
                                ->disabled(),
                            TextInput::make('target')
                                ->label('Target')
                                ->visible(fn () => $record->rule_type !== SiteFirewallRule::TYPE_COUNTRY)
                                ->required(fn () => $record->rule_type !== SiteFirewallRule::TYPE_COUNTRY),
                            Select::make('country_codes')
                                ->label('Countries')
                                ->options(function (): array {
                                    $site = $this->getSelectedSite();

                                    if (! $site) {
                                        return [];
                                    }

                                    return app(FirewallAccessControlService::class)->countryOptions($site);
                                })
                                ->multiple()
                                ->searchable()
                                ->helperText('Select one or more countries for this rule set.')
                                ->visible(fn () => $record->rule_type === SiteFirewallRule::TYPE_COUNTRY),
                            Select::make('action')
                                ->label('Action')
                                ->options(SiteFirewallRule::actionOptions())
                                ->required(),
                            Select::make('mode')
                                ->label('Mode')
                                ->options([
                                    SiteFirewallRule::MODE_ENFORCED => 'Enforced',
                                    SiteFirewallRule::MODE_STAGED => 'Staged',
                                ])
                                ->required(),
                            DateTimePicker::make('expires_at')
                                ->label('Temporary until')
                                ->native(false)
                                ->seconds(false),
                            Textarea::make('note')
                                ->label('Reason (optional)')
                                ->rows(2)
                                ->maxLength(255),
                        ];
                    })
                    ->action(function (SiteFirewallRule $record, array $data): void {
                        if ($record->rule_type === SiteFirewallRule::TYPE_COUNTRY) {
                            $codes = collect((array) ($data['country_codes'] ?? []))
                                ->map(fn (string $line): string => strtoupper(trim($line)))
                                ->filter()
                                ->unique()
                                ->values();

                            $invalid = $codes->first(fn (string $code): bool => preg_match('/^[A-Z]{2}$/', $code) !== 1);
                            if ($invalid) {
                                Notification::make()
                                    ->title('Invalid country code in list')
                                    ->body("`{$invalid}` is not a valid 2-letter country code.")
                                    ->warning()
                                    ->send();

                                return;
                            }
                        }

                        $expiresAt = $data['expires_at'] ?? null;
                        app(FirewallAccessControlService::class)->updateRule(
                            $record,
                            (int) auth()->id(),
                            [
                                'target' => $data['target'] ?? null,
                                'countries_content' => collect((array) ($data['country_codes'] ?? []))
                                    ->map(fn (string $line): string => strtoupper(trim($line)))
                                    ->filter()
                                    ->unique()
                                    ->implode("\n"),
                                'action' => $data['action'] ?? $record->action,
                                'mode' => $data['mode'] ?? $record->mode,
                                'note' => $data['note'] ?? null,
                                'expires_at' => $expiresAt ? Carbon::parse((string) $expiresAt) : null,
                            ],
                        );

                        Notification::make()->title('Rule updated.')->success()->send();
                        $this->dispatch('firewall-access-rules-updated');
                    }),
                Action::make('enforce')
                    ->label('Enforce now')
                    ->icon('heroicon-m-check-circle')
                    ->visible(fn (SiteFirewallRule $record): bool => $record->mode === SiteFirewallRule::MODE_STAGED)
                    ->action(function (SiteFirewallRule $record): void {
                        app(FirewallAccessControlService::class)->applyRule($record, (int) auth()->id());
                        Notification::make()->title('Rule enforced.')->success()->send();
                        $this->dispatch('firewall-access-rules-updated');
                    }),
                Action::make('disable')
                    ->label('Disable')
                    ->icon('heroicon-m-pause-circle')
                    ->color('gray')
                    ->visible(fn (SiteFirewallRule $record): bool => $record->status === SiteFirewallRule::STATUS_ACTIVE)
                    ->requiresConfirmation()
                    ->action(function (SiteFirewallRule $record): void {
                        app(FirewallAccessControlService::class)->disableRule($record, (int) auth()->id());
                        Notification::make()->title('Rule disabled.')->success()->send();
                        $this->dispatch('firewall-access-rules-updated');
                    }),
                Action::make('enable')
                    ->label('Enable')
                    ->icon('heroicon-m-play-circle')
                    ->visible(fn (SiteFirewallRule $record): bool => $record->status === SiteFirewallRule::STATUS_REMOVED)
                    ->action(function (SiteFirewallRule $record): void {
                        app(FirewallAccessControlService::class)->enableRule($record, (int) auth()->id());
                        Notification::make()->title('Rule enabled.')->success()->send();
                        $this->dispatch('firewall-access-rules-updated');
                    }),
                Action::make('remove')
                    ->label('Remove')
                    ->color('danger')
                    ->icon('heroicon-m-trash')
                    ->requiresConfirmation()
                    ->action(function (SiteFirewallRule $record): void {
                        app(FirewallAccessControlService::class)->removeRule($record, (int) auth()->id());
                        Notification::make()->title('Rule removed.')->success()->send();
                        $this->dispatch('firewall-access-rules-updated');
                    }),
            ])
            ->emptyStateHeading('No access rules yet')
            ->emptyStateDescription('Create country, continent, IP, or CIDR rules above to start controlling access.');
    }

    protected function query()
    {
        $site = $this->getSelectedSite();

        if (! $site) {
            return SiteFirewallRule::query()->whereRaw('1 = 0');
        }

        return SiteFirewallRule::query()->where('site_id', $site->id);
    }
}
