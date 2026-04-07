<?php

namespace App\Filament\Admin\Pages;

use App\Services\Seo\LlmsTxtService;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class LlmsTxtPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static string|\UnitEnum|null $navigationGroup = 'Marketing';

    protected static ?string $navigationLabel = 'llms.txt';

    protected static ?string $title = 'llms.txt';

    protected static ?int $navigationSort = 22;

    protected string $view = 'filament.admin.pages.llms-txt';

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
            'template' => app(LlmsTxtService::class)->template(),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Static llms.txt content')
                    ->description('Edit the static content for /llms.txt. Keep {{blog_posts}} in place so published blog posts continue to appear automatically.')
                    ->schema([
                        Textarea::make('template')
                            ->label('Template')
                            ->rows(32)
                            ->autosize()
                            ->required()
                            ->helperText('Use plain text / markdown style content. The {{blog_posts}} placeholder is replaced automatically with all currently published blog posts.'),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        app(LlmsTxtService::class)->saveTemplate((string) ($this->data['template'] ?? ''));

        Notification::make()
            ->title('llms.txt content saved.')
            ->success()
            ->send();
    }

    public function resetToDefault(): void
    {
        $this->form->fill([
            'template' => app(LlmsTxtService::class)->defaultTemplate(),
        ]);

        Notification::make()
            ->title('Default llms.txt template loaded. Save to apply it.')
            ->success()
            ->send();
    }
}
