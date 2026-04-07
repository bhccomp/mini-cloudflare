<?php

namespace App\Filament\Admin\Pages;

use App\Services\Bunny\BunnyEdgeErrorPageService;
use Filament\Pages\Page;

class EdgeErrorPreviewPage extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-window';

    protected static string|\UnitEnum|null $navigationGroup = 'System';

    protected static ?string $navigationLabel = 'Edge Error Preview';

    protected static ?string $title = 'Edge Error Preview';

    protected static ?int $navigationSort = 21;

    protected string $view = 'filament.admin.pages.edge-error-preview';

    public string $template = '5xx';

    public string $domain = 'preview.firephage.com';

    public string $html = '';

    public function mount(): void
    {
        $template = (string) request()->query('template', '5xx');
        $domain = trim((string) request()->query('domain', 'preview.firephage.com'));

        $this->template = in_array($template, ['403', '404', '429', '5xx'], true) ? $template : '5xx';
        $this->domain = $domain !== '' ? $domain : 'preview.firephage.com';
        $this->html = app(BunnyEdgeErrorPageService::class)->buildPreviewHtml($this->template, $this->domain);
    }

    /**
     * @return array<string, string>
     */
    public function templates(): array
    {
        return [
            '5xx' => '502 / 504',
            '403' => '403',
            '404' => '404',
            '429' => '429',
        ];
    }
}
