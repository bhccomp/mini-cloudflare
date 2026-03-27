<?php

namespace App\Filament\App\Widgets\Firewall;

use App\Filament\App\Widgets\Concerns\ResolvesSelectedSite;
use App\Models\SiteFirewallRule;
use App\Services\Bunny\Waf\BunnyShieldWafService;
use App\Services\Firewall\FirewallAccessControlService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Carbon;
use Illuminate\Support\HtmlString;
use Livewire\Attributes\On;

class FirewallAccessRulesTable extends TableWidget
{
    use ResolvesSelectedSite;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Configured Access Rules';

    #[On('firewall-access-rules-updated')]
    public function refreshRulesTable(): void
    {
        // Listener triggers component refresh.
    }

    protected function getTableHeading(): string|Htmlable|null
    {
        return new HtmlString('<span id="configured-access-rules">Configured Access Rules</span>');
    }

    public function table(Table $table): Table
    {
        return $table
            ->description('Staged and enforced rules with expiration and provider status.')
            ->query($this->query())
            ->defaultSort('id', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('rule_label')
                    ->label('Rule')
                    ->state(function (SiteFirewallRule $record): HtmlString {
                        $label = $record->rule_type === SiteFirewallRule::TYPE_ADVANCED
                            ? (string) data_get($record->meta, 'display_name', 'Advanced rule')
                            : (string) data_get($record->meta, 'display_name', $record->target);

                        $description = $record->rule_type === SiteFirewallRule::TYPE_ADVANCED
                            ? $record->note
                            : ($record->target !== (string) data_get($record->meta, 'display_name', $record->target) ? $record->target : null);

                        $escapedLabel = e($label);

                        if (! is_string($description) || trim($description) === '') {
                            return new HtmlString($escapedLabel);
                        }

                        $escapedDescription = e($description);

                        return new HtmlString(
                            $escapedLabel.' <span class="ml-1 inline-flex h-4 w-4 items-center justify-center rounded-full border border-gray-300 text-[10px] font-semibold leading-none text-gray-500 dark:border-gray-600 dark:text-gray-400">?</span>'
                        );
                    })
                    ->html()
                    ->tooltip(function (SiteFirewallRule $record): ?string {
                        if ($record->rule_type === SiteFirewallRule::TYPE_ADVANCED) {
                            return $record->note ?: null;
                        }

                        $displayName = (string) data_get($record->meta, 'display_name', $record->target);

                        return $record->target !== $displayName ? $record->target : null;
                    })
                    ->wrap()
                    ->searchable(query: function ($query, string $search): void {
                        $query->where(function ($subQuery) use ($search): void {
                            $subQuery
                                ->where('target', 'like', "%{$search}%")
                                ->orWhere('note', 'like', "%{$search}%");
                        });
                    }),
                Tables\Columns\TextColumn::make('rule_type')
                    ->label('Type')
                    ->formatStateUsing(fn (string $state, SiteFirewallRule $record): string => $record->rule_type === SiteFirewallRule::TYPE_ADVANCED
                        ? 'Advanced Rule'
                        : (SiteFirewallRule::typeOptions()[$state] ?? str($state)->headline()->toString()))
                    ->badge(),
                Tables\Columns\TextColumn::make('target')
                    ->label('Target')
                    ->formatStateUsing(function (string $state, SiteFirewallRule $record): string {
                        if ($record->rule_type === SiteFirewallRule::TYPE_ADVANCED) {
                            return (string) data_get($record->meta, 'display_match', $state);
                        }

                        $count = (int) data_get($record->meta, 'entry_count', 0);
                        $isLegacySetLabel = preg_match('/\bset\s*\(\d+\)\b/i', $state) === 1;

                        if ($count > 1 || $isLegacySetLabel) {
                            $typeLabel = match ($record->rule_type) {
                                SiteFirewallRule::TYPE_COUNTRY => 'Country',
                                SiteFirewallRule::TYPE_CONTINENT => 'Continent',
                                SiteFirewallRule::TYPE_IP => 'IP',
                                SiteFirewallRule::TYPE_CIDR => 'CIDR',
                                SiteFirewallRule::TYPE_ASN => 'ASN',
                                default => 'Access',
                            };

                            $suffix = match (strtolower($record->action)) {
                                SiteFirewallRule::ACTION_ALLOW => 'Allowlist',
                                SiteFirewallRule::ACTION_CHALLENGE => 'Challenges',
                                SiteFirewallRule::ACTION_LOG => 'Logs',
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
                    ->formatStateUsing(fn ($state, SiteFirewallRule $record): string => $record->rule_type === SiteFirewallRule::TYPE_ADVANCED ? '1' : ($state ? (string) $state : '1'))
                    ->badge()
                    ->color('gray'),
                Tables\Columns\TextColumn::make('action')
                    ->label('Action')
                    ->formatStateUsing(fn (string $state, SiteFirewallRule $record): string => $record->rule_type === SiteFirewallRule::TYPE_ADVANCED
                        ? (string) data_get($record->meta, 'display_action', 'Configured')
                        : str($state)->headline()->toString())
                    ->badge()
                    ->color(fn (string $state, SiteFirewallRule $record): string => match (strtolower($record->rule_type === SiteFirewallRule::TYPE_ADVANCED ? (string) data_get($record->meta, 'display_action', $state) : $state)) {
                        'allow' => 'success',
                        'challenge' => 'warning',
                        'log only' => 'info',
                        'log' => 'info',
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
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->since(),
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
                ActionGroup::make([
                Action::make('edit')
                    ->label('Edit')
                    ->icon('heroicon-m-pencil-square')
                    ->visible(fn (SiteFirewallRule $record): bool => $record->rule_type !== SiteFirewallRule::TYPE_ADVANCED)
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
                            'rule_name' => data_get($record->meta, 'display_name'),
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
                            TextInput::make('rule_name')
                                ->label('Rule name (optional)')
                                ->maxLength(120),
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
                                'display_name' => $data['rule_name'] ?? null,
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
                Action::make('editAdvanced')
                    ->label('Edit')
                    ->icon('heroicon-m-pencil-square')
                    ->visible(fn (SiteFirewallRule $record): bool => $record->rule_type === SiteFirewallRule::TYPE_ADVANCED)
                    ->fillForm(function (SiteFirewallRule $record): array {
                        return [
                            'advanced_rule_name' => (string) data_get($record->meta, 'display_name', 'Advanced rule'),
                            'advanced_rule_variable' => (string) data_get($record->meta, 'variable', 'REQUEST_URI'),
                            'advanced_rule_operator' => (string) data_get($record->meta, 'operator', 'contains'),
                            'advanced_rule_value' => (string) data_get($record->meta, 'display_match', $record->target),
                            'advanced_rule_transformation' => (string) data_get($record->meta, 'transformation', 'lower'),
                            'advanced_rule_action' => $this->normalizeAdvancedActionToKey((string) data_get($record->meta, 'display_action', 'block')),
                            'advanced_rule_description' => (string) ($record->note ?? ''),
                            'advanced_rule_conditions' => (array) data_get($record->meta, 'conditions', []),
                        ];
                    })
                    ->form([
                        TextInput::make('advanced_rule_name')
                            ->label('Rule name')
                            ->maxLength(120)
                            ->required(),
                        Select::make('advanced_rule_variable')
                            ->label('Request field')
                            ->options([
                                'REQUEST_URI' => 'Request path',
                                'REMOTE_ADDR' => 'Visitor IP',
                                'GEO' => 'Country code',
                                'REQUEST_METHOD' => 'HTTP method',
                                'REQUEST_HEADERS' => 'Request headers',
                                'REQUEST_COOKIES' => 'Cookies',
                                'QUERY_STRING' => 'Query string',
                                'REQUEST_BODY' => 'Request body',
                            ])
                            ->searchable()
                            ->required(),
                        Select::make('advanced_rule_operator')
                            ->label('Comparison')
                            ->options([
                                'contains' => 'Contains',
                                'equals' => 'Equals',
                                'startswith' => 'Starts with',
                                'endswith' => 'Ends with',
                                'regex' => 'Regex',
                            ])
                            ->searchable()
                            ->required(),
                        TextInput::make('advanced_rule_value')
                            ->label('Match value')
                            ->required()
                            ->columnSpanFull(),
                        Select::make('advanced_rule_transformation')
                            ->label('Normalization')
                            ->options([
                                'none' => 'No transform',
                                'lower' => 'Lowercase',
                                'trim' => 'Trim whitespace',
                            ])
                            ->searchable()
                            ->required(),
                        Select::make('advanced_rule_action')
                            ->label('Response')
                            ->options([
                                'block' => 'Block',
                                'challenge' => 'Challenge',
                                'log' => 'Log only',
                            ])
                            ->searchable()
                            ->required(),
                        TextInput::make('advanced_rule_description')
                            ->label('Why this rule exists')
                            ->maxLength(255)
                            ->columnSpanFull(),
                        Repeater::make('advanced_rule_conditions')
                            ->label('Additional conditions')
                            ->default([])
                            ->collapsed()
                            ->addActionLabel('Add AND condition')
                            ->schema([
                                Select::make('variable')
                                    ->label('Request field')
                                    ->options([
                                        'REQUEST_URI' => 'Request path',
                                        'REMOTE_ADDR' => 'Visitor IP',
                                        'GEO' => 'Country code',
                                        'REQUEST_METHOD' => 'HTTP method',
                                        'REQUEST_HEADERS' => 'Request headers',
                                        'REQUEST_COOKIES' => 'Cookies',
                                        'QUERY_STRING' => 'Query string',
                                        'REQUEST_BODY' => 'Request body',
                                    ])
                                    ->searchable()
                                    ->required(),
                                Select::make('operator')
                                    ->label('Comparison')
                                    ->options([
                                        'contains' => 'Contains',
                                        'equals' => 'Equals',
                                        'startswith' => 'Starts with',
                                        'endswith' => 'Ends with',
                                        'regex' => 'Regex',
                                    ])
                                    ->searchable()
                                    ->required(),
                                TextInput::make('value')
                                    ->label('Match value')
                                    ->required()
                                    ->columnSpanFull(),
                                Select::make('transformation')
                                    ->label('Normalization')
                                    ->options([
                                        'none' => 'No transform',
                                        'lower' => 'Lowercase',
                                        'trim' => 'Trim whitespace',
                                    ])
                                    ->searchable()
                                    ->default('lower'),
                            ])
                            ->columnSpanFull(),
                    ])
                    ->action(function (SiteFirewallRule $record, array $data): void {
                        $conditions = array_values(array_filter((array) ($data['advanced_rule_conditions'] ?? []), fn (mixed $condition): bool => is_array($condition)));

                        $updated = app(BunnyShieldWafService::class)->updateCustomRule($record->site, (string) $record->provider_rule_id, [
                            'name' => (string) ($data['advanced_rule_name'] ?? 'Advanced rule'),
                            'description' => (string) ($data['advanced_rule_description'] ?? ''),
                            'action' => (string) ($data['advanced_rule_action'] ?? 'block'),
                            'variable' => (string) ($data['advanced_rule_variable'] ?? 'REQUEST_URI'),
                            'operator' => (string) ($data['advanced_rule_operator'] ?? 'contains'),
                            'transformation' => (string) ($data['advanced_rule_transformation'] ?? 'lower'),
                            'value' => (string) ($data['advanced_rule_value'] ?? ''),
                            'conditions' => $conditions,
                        ]);

                        $record->update([
                            'target' => (string) ($data['advanced_rule_value'] ?? $record->target),
                            'note' => (string) ($data['advanced_rule_description'] ?? ''),
                            'meta' => array_merge(is_array($record->meta) ? $record->meta : [], [
                                'advanced_rule' => true,
                                'display_name' => (string) ($data['advanced_rule_name'] ?? 'Advanced rule'),
                                'display_action' => str((string) data_get($updated, 'actionLabel', $data['advanced_rule_action'] ?? 'block'))->headline()->toString(),
                                'display_match' => (string) ($data['advanced_rule_value'] ?? $record->target),
                                'variable' => (string) ($data['advanced_rule_variable'] ?? 'REQUEST_URI'),
                                'operator' => (string) ($data['advanced_rule_operator'] ?? 'contains'),
                                'transformation' => (string) ($data['advanced_rule_transformation'] ?? 'lower'),
                                'conditions' => $conditions,
                            ]),
                        ]);

                        Notification::make()->title('Advanced rule updated.')->success()->send();
                        $this->dispatch('firewall-access-rules-updated');
                    }),
                Action::make('enforce')
                    ->label('Enforce now')
                    ->icon('heroicon-m-check-circle')
                    ->visible(fn (SiteFirewallRule $record): bool => $record->rule_type !== SiteFirewallRule::TYPE_ADVANCED && $record->mode === SiteFirewallRule::MODE_STAGED)
                    ->action(function (SiteFirewallRule $record): void {
                        app(FirewallAccessControlService::class)->applyRule($record, (int) auth()->id());
                        Notification::make()->title('Rule enforced.')->success()->send();
                        $this->dispatch('firewall-access-rules-updated');
                    }),
                Action::make('disable')
                    ->label('Disable')
                    ->icon('heroicon-m-pause-circle')
                    ->color('gray')
                    ->visible(fn (SiteFirewallRule $record): bool => $record->rule_type !== SiteFirewallRule::TYPE_ADVANCED
                        ? $record->status === SiteFirewallRule::STATUS_ACTIVE
                        : $record->status === SiteFirewallRule::STATUS_ACTIVE)
                    ->requiresConfirmation()
                    ->action(function (SiteFirewallRule $record): void {
                        if ($record->rule_type === SiteFirewallRule::TYPE_ADVANCED) {
                            app(BunnyShieldWafService::class)->deleteCustomRule((string) $record->provider_rule_id);
                            $meta = is_array($record->meta) ? $record->meta : [];
                            $record->update([
                                'provider_rule_id' => null,
                                'status' => SiteFirewallRule::STATUS_REMOVED,
                                'meta' => array_merge($meta, [
                                    'disabled_at' => now()->toIso8601String(),
                                ]),
                            ]);
                        } else {
                            app(FirewallAccessControlService::class)->disableRule($record, (int) auth()->id());
                        }

                        Notification::make()->title('Rule disabled.')->success()->send();
                        $this->dispatch('firewall-access-rules-updated');
                    }),
                Action::make('enable')
                    ->label('Enable')
                    ->icon('heroicon-m-play-circle')
                    ->visible(fn (SiteFirewallRule $record): bool => $record->rule_type !== SiteFirewallRule::TYPE_ADVANCED
                        ? $record->status === SiteFirewallRule::STATUS_REMOVED
                        : $record->status === SiteFirewallRule::STATUS_REMOVED)
                    ->action(function (SiteFirewallRule $record): void {
                        if ($record->rule_type === SiteFirewallRule::TYPE_ADVANCED) {
                            $created = app(BunnyShieldWafService::class)->createCustomRule($record->site, [
                                'name' => (string) data_get($record->meta, 'display_name', 'Advanced rule'),
                                'description' => (string) ($record->note ?? ''),
                                'action' => $this->normalizeAdvancedActionToKey((string) data_get($record->meta, 'display_action', 'block')),
                                'variable' => (string) data_get($record->meta, 'variable', 'REQUEST_URI'),
                                'operator' => (string) data_get($record->meta, 'operator', 'contains'),
                                'transformation' => (string) data_get($record->meta, 'transformation', 'lower'),
                                'value' => (string) data_get($record->meta, 'display_match', $record->target),
                                'conditions' => (array) data_get($record->meta, 'conditions', []),
                            ]);

                            $meta = is_array($record->meta) ? $record->meta : [];
                            unset($meta['disabled_at']);
                            $record->update([
                                'provider_rule_id' => (string) data_get($created, 'wafRuleId', data_get($created, 'id', '')),
                                'status' => SiteFirewallRule::STATUS_ACTIVE,
                                'meta' => array_merge($meta, [
                                    'display_action' => str((string) data_get($created, 'actionLabel', data_get($record->meta, 'display_action', 'Block')))->headline()->toString(),
                                ]),
                            ]);
                        } else {
                            app(FirewallAccessControlService::class)->enableRule($record, (int) auth()->id());
                        }

                        Notification::make()->title('Rule enabled.')->success()->send();
                        $this->dispatch('firewall-access-rules-updated');
                    }),
                Action::make('remove')
                    ->label('Remove')
                    ->color('danger')
                    ->icon('heroicon-m-trash')
                    ->requiresConfirmation()
                    ->action(function (SiteFirewallRule $record): void {
                        if ($record->rule_type === SiteFirewallRule::TYPE_ADVANCED) {
                            app(BunnyShieldWafService::class)->deleteCustomRule((string) $record->provider_rule_id);
                            $record->delete();
                        } else {
                            app(FirewallAccessControlService::class)->removeRule($record, (int) auth()->id());
                        }

                        Notification::make()->title('Rule removed.')->success()->send();
                        $this->dispatch('firewall-access-rules-updated');
                    }),
                ])
                    ->label('Actions')
                    ->icon('heroicon-m-ellipsis-horizontal')
                    ->button()
                    ->size('sm'),
            ])
            ->emptyStateHeading('No rules yet')
            ->emptyStateDescription('Create access or advanced request rules above to start protecting the site.');
    }

    protected function query()
    {
        $site = $this->getSelectedSite();

        if (! $site) {
            return SiteFirewallRule::query()->whereRaw('1 = 0');
        }

        return SiteFirewallRule::query()
            ->where('site_id', $site->id)
            ->when(
                ! $this->siteHasAdvancedWafSupport($site),
                fn ($query) => $query->where('rule_type', '!=', SiteFirewallRule::TYPE_ADVANCED),
            );
    }

    protected function normalizeAdvancedActionToKey(string $value): string
    {
        return match (strtolower(trim($value))) {
            'block', '1' => 'block',
            'challenge', '2' => 'challenge',
            'log', 'log only', '3' => 'log',
            default => 'block',
        };
    }

    protected function siteHasAdvancedWafSupport($site): bool
    {
        $meta = is_array($site->provider_meta) ? $site->provider_meta : [];

        if (((int) ($meta['shield_plan_type'] ?? 0)) > 0) {
            return true;
        }

        if (($meta['shield_plan_status'] ?? null) === 'active') {
            return true;
        }

        if (($meta['shield_plan'] ?? null) === 'advanced') {
            return true;
        }

        return (bool) ($meta['shield_premium_plan'] ?? false);
    }
}
