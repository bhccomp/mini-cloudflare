<?php

namespace App\Filament\App\Resources\AlertChannelResource\Pages;

use App\Filament\App\Resources\AlertChannelResource;
use App\Models\AlertChannel;
use App\Models\Site;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class ManageAlertChannels extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $resource = AlertChannelResource::class;

    protected string $view = 'filament.app.resources.alert-channel-resource.pages.manage-alert-channels';

    public ?array $data = [];

    /**
     * @var array<int, string>
     */
    public array $siteOptions = [];

    public function mount(): void
    {
        $this->siteOptions = $this->resolveSiteOptions();

        $this->form->fill($this->loadState());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Tabs::make('channels')
                    ->tabs([
                        Tab::make('Slack')
                            ->icon('heroicon-o-chat-bubble-left-right')
                            ->schema([
                                Toggle::make('slack.enabled')
                                    ->label('Enable Slack alerts')
                                    ->default(false),
                                Select::make('slack.site_id')
                                    ->label('Site scope')
                                    ->options($this->siteOptions)
                                    ->searchable()
                                    ->placeholder('All sites (organization-wide)'),
                                TextInput::make('slack.webhook_url')
                                    ->label('Webhook URL')
                                    ->url()
                                    ->maxLength(500)
                                    ->placeholder('https://hooks.slack.com/services/...')
                                    ->helperText('Incoming webhook where alert messages will be delivered.'),
                                TextInput::make('slack.channel')
                                    ->label('Channel override (optional)')
                                    ->maxLength(100)
                                    ->placeholder('#security-alerts'),
                                TextInput::make('slack.mention')
                                    ->label('Mention (optional)')
                                    ->maxLength(120)
                                    ->placeholder('@on-call'),
                            ]),
                        Tab::make('Email')
                            ->icon('heroicon-o-envelope')
                            ->schema([
                                Toggle::make('email.enabled')
                                    ->label('Enable email alerts')
                                    ->default(true),
                                Select::make('email.site_id')
                                    ->label('Site scope')
                                    ->options($this->siteOptions)
                                    ->searchable()
                                    ->placeholder('All sites (organization-wide)'),
                                Textarea::make('email.recipients')
                                    ->label('Recipients')
                                    ->rows(4)
                                    ->placeholder("ops@example.com\nsecurity@example.com")
                                    ->helperText('One email per line.'),
                                TextInput::make('email.from_name')
                                    ->label('From name')
                                    ->maxLength(120)
                                    ->placeholder('FirePhage Alerts'),
                            ]),
                        Tab::make('Webhook')
                            ->icon('heroicon-o-link')
                            ->schema([
                                Toggle::make('webhook.enabled')
                                    ->label('Enable generic webhook alerts')
                                    ->default(false),
                                Select::make('webhook.site_id')
                                    ->label('Site scope')
                                    ->options($this->siteOptions)
                                    ->searchable()
                                    ->placeholder('All sites (organization-wide)'),
                                TextInput::make('webhook.url')
                                    ->label('Destination URL')
                                    ->url()
                                    ->maxLength(500)
                                    ->placeholder('https://example.com/alerts'),
                                TextInput::make('webhook.secret')
                                    ->label('Signing secret (optional)')
                                    ->password()
                                    ->revealable(),
                                Placeholder::make('webhook_delivery_reference')
                                    ->label('Webhook delivery reference')
                                    ->content(fn (): HtmlString => new HtmlString($this->webhookDeliveryReferenceHtml())),
                            ]),
                    ])
                    ->columnSpanFull(),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $state = $this->form->getState();
        $organizationId = $this->organizationId();

        if (! $organizationId) {
            Notification::make()
                ->title('Unable to save alert settings.')
                ->body('No organization context found for this user.')
                ->danger()
                ->send();

            return;
        }

        $this->upsertChannel(
            organizationId: $organizationId,
            type: 'slack',
            name: 'Slack Alerts',
            enabled: (bool) data_get($state, 'slack.enabled', false),
            siteId: data_get($state, 'slack.site_id'),
            config: [
                'webhook_url' => (string) data_get($state, 'slack.webhook_url', ''),
                'channel' => (string) data_get($state, 'slack.channel', ''),
                'mention' => (string) data_get($state, 'slack.mention', ''),
            ],
        );

        $this->upsertChannel(
            organizationId: $organizationId,
            type: 'email',
            name: 'Email Alerts',
            enabled: (bool) data_get($state, 'email.enabled', false),
            siteId: data_get($state, 'email.site_id'),
            config: [
                'recipients' => $this->lines((string) data_get($state, 'email.recipients', '')),
                'from_name' => (string) data_get($state, 'email.from_name', ''),
            ],
        );

        $this->upsertChannel(
            organizationId: $organizationId,
            type: 'webhook',
            name: 'Webhook Alerts',
            enabled: (bool) data_get($state, 'webhook.enabled', false),
            siteId: data_get($state, 'webhook.site_id'),
            config: [
                'url' => (string) data_get($state, 'webhook.url', ''),
                'secret' => (string) data_get($state, 'webhook.secret', ''),
            ],
        );

        Notification::make()
            ->title('Alert channel settings saved.')
            ->success()
            ->send();
    }

    /**
     * @return array<string, mixed>
     */
    protected function loadState(): array
    {
        $organizationId = $this->organizationId();
        if (! $organizationId) {
            return $this->defaultState();
        }

        $channels = AlertChannel::query()
            ->where('organization_id', $organizationId)
            ->whereIn('type', ['slack', 'email', 'webhook'])
            ->orderBy('id')
            ->get()
            ->groupBy('type')
            ->map(fn ($group) => $group->first());

        $defaults = $this->defaultState();

        foreach (['slack', 'email', 'webhook'] as $type) {
            $record = $channels->get($type);
            if (! $record) {
                continue;
            }

            $defaults[$type]['enabled'] = (bool) $record->is_active;
            $defaults[$type]['site_id'] = $record->site_id;
            $defaults[$type] = array_merge($defaults[$type], is_array($record->config) ? $record->config : []);
            if (isset($defaults[$type]['recipients']) && is_array($defaults[$type]['recipients'])) {
                $defaults[$type]['recipients'] = implode("\n", array_filter($defaults[$type]['recipients']));
            }
        }

        return $defaults;
    }

    /**
     * @return array<int, string>
     */
    protected function resolveSiteOptions(): array
    {
        $organizationIds = auth()->user()?->organizations()->pluck('organizations.id');

        if (! $organizationIds || $organizationIds->isEmpty()) {
            return [];
        }

        return Site::query()
            ->whereIn('organization_id', $organizationIds)
            ->orderBy('apex_domain')
            ->pluck('apex_domain', 'id')
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultState(): array
    {
        return [
            'slack' => [
                'enabled' => false,
                'site_id' => null,
                'webhook_url' => '',
                'channel' => '',
                'mention' => '',
            ],
            'email' => [
                'enabled' => true,
                'site_id' => null,
                'recipients' => '',
                'from_name' => 'FirePhage Alerts',
            ],
            'webhook' => [
                'enabled' => false,
                'site_id' => null,
                'url' => '',
                'secret' => '',
            ],
        ];
    }

    protected function webhookDeliveryReferenceHtml(): string
    {
        $sample = [
            'source' => 'firephage-wordpress-plugin',
            'event' => 'wordpress_malware_detected',
            'payload' => [
                'event' => 'wordpress_malware_detected',
                'title' => 'Malware detected',
                'site' => 'example.com',
                'site_id' => 123,
                'scan_id' => 'scan_abc123',
                'summary' => '3 malicious file(s) reported.',
                'malicious_files' => 3,
                'integrity_issues' => 1,
                'findings' => [
                    'wp-content/uploads/evil.php',
                    'index.php',
                ],
                'dashboard_url' => rtrim((string) config('app.url'), '/') . '/app/wordpress?site_id=123',
            ],
        ];

        return sprintf(
            '<div class="space-y-3 text-sm text-gray-600 dark:text-gray-300">
                <p>FirePhage sends a <strong>POST</strong> request with a JSON body when a connected WordPress site reports malware or modified core files.</p>
                <ul class="list-disc space-y-1 pl-5">
                    <li><strong>Content-Type:</strong> <code>application/json</code></li>
                    <li><strong>Events:</strong> <code>wordpress_malware_detected</code>, <code>wordpress_core_edits_detected</code></li>
                    <li><strong>Signature header:</strong> <code>X-FirePhage-Signature</code> when a signing secret is set</li>
                </ul>
                <details class="rounded-lg border border-gray-200 bg-white p-3 dark:border-white/10 dark:bg-white/5">
                    <summary class="cursor-pointer font-medium text-gray-900 dark:text-white">View sample payload</summary>
                    <pre class="mt-3 overflow-x-auto rounded-md bg-gray-950/95 p-3 text-xs leading-5 text-gray-100">%s</pre>
                </details>
            </div>',
            e(json_encode($sample, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES))
        );
    }

    protected function organizationId(): ?int
    {
        $user = auth()->user();
        if (! $user) {
            return null;
        }

        $current = $user->current_organization_id;
        if (is_numeric($current)) {
            return (int) $current;
        }

        $fallback = $user->organizations()->value('organizations.id');

        return is_numeric($fallback) ? (int) $fallback : null;
    }

    /**
     * @return array<int, string>
     */
    protected function lines(string $value): array
    {
        $rows = preg_split('/\r\n|\r|\n/', $value) ?: [];

        return array_values(array_filter(array_map('trim', $rows)));
    }

    /**
     * @param  array<string, mixed>  $config
     */
    protected function upsertChannel(
        int $organizationId,
        string $type,
        string $name,
        bool $enabled,
        mixed $siteId,
        array $config
    ): void {
        $record = AlertChannel::query()
            ->where('organization_id', $organizationId)
            ->where('type', $type)
            ->orderBy('id')
            ->first();

        if (! $record) {
            AlertChannel::query()->create([
                'organization_id' => $organizationId,
                'site_id' => is_numeric($siteId) ? (int) $siteId : null,
                'name' => $name,
                'type' => $type,
                'is_active' => $enabled,
                'config' => $config,
            ]);

            return;
        }

        $record->update([
            'site_id' => is_numeric($siteId) ? (int) $siteId : null,
            'name' => $name,
            'is_active' => $enabled,
            'config' => $config,
        ]);
    }
}
