<?php

namespace App\Filament\Admin\Pages;

use App\Services\Seo\SitemapService;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SitemapPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-map';

    protected static string|\UnitEnum|null $navigationGroup = 'Marketing';

    protected static ?string $navigationLabel = 'Sitemap';

    protected static ?string $title = 'Sitemap';

    protected static ?int $navigationSort = 24;

    protected string $view = 'filament.admin.pages.sitemap';

    public ?array $data = [];

    protected function getForms(): array
    {
        return [
            'form',
        ];
    }

    public function mount(): void
    {
        $this->form->fill([
            'selected_urls' => app(SitemapService::class)->selectedUrls(),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Included URLs')
                    ->description('Public URLs are detected automatically. New pages, service pages, and published blog posts appear here checked by default. Uncheck any URL you want excluded from /sitemap.xml.')
                    ->schema([
                        CheckboxList::make('selected_urls')
                            ->label('URLs')
                            ->options(app(SitemapService::class)->checkboxOptions())
                            ->bulkToggleable()
                            ->columns(1)
                            ->searchable()
                            ->helperText('Unchecked URLs are stored as exclusions. If a new public page appears later, it will be added automatically and stay included unless you uncheck it.'),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        app(SitemapService::class)->saveSelectedUrls((array) ($this->data['selected_urls'] ?? []));

        Notification::make()
            ->title('Sitemap settings saved.')
            ->success()
            ->send();
    }
}
