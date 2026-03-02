<?php

namespace App\Filament\App\Widgets\Firewall;

use App\Filament\App\Widgets\Concerns\ResolvesSelectedSite;
use App\Models\SiteFirewallRule;
use App\Services\Firewall\FirewallAccessControlService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
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
                    ->copyable()
                    ->searchable(),
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
                        SiteFirewallRule::STATUS_EXPIRED, SiteFirewallRule::STATUS_REMOVED => 'gray',
                        SiteFirewallRule::STATUS_FAILED => 'danger',
                        default => 'warning',
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
                        SiteFirewallRule::STATUS_FAILED => 'Failed',
                        SiteFirewallRule::STATUS_EXPIRED => 'Expired',
                        SiteFirewallRule::STATUS_REMOVED => 'Removed',
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
                Action::make('enforce')
                    ->label('Enforce now')
                    ->icon('heroicon-m-check-circle')
                    ->visible(fn (SiteFirewallRule $record): bool => $record->mode === SiteFirewallRule::MODE_STAGED && $record->status !== SiteFirewallRule::STATUS_REMOVED)
                    ->action(function (SiteFirewallRule $record): void {
                        app(FirewallAccessControlService::class)->applyRule($record, (int) auth()->id());
                        Notification::make()->title('Rule enforced.')->success()->send();
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
