<?php

namespace App\Livewire\Filament\App;

use App\Services\UiModeManager;
use Filament\Notifications\Notification;
use Livewire\Attributes\On;
use Livewire\Component;

class UiModeSwitcher extends Component
{
    public string $mode = UiModeManager::SIMPLE;

    public function mount(UiModeManager $uiMode): void
    {
        $this->mode = $uiMode->current();
    }

    public function setMode(string $mode, UiModeManager $uiMode): void
    {
        $user = auth()->user();

        if (! $user) {
            return;
        }

        $this->mode = $uiMode->setMode($user, $mode);

        Notification::make()
            ->title('Switched to '.($this->mode === UiModeManager::PRO ? 'Pro' : 'Simple').' mode')
            ->success()
            ->send();

        $this->dispatch('ui-mode-changed', mode: $this->mode);
    }

    #[On('ui-mode-changed')]
    public function syncMode(string $mode = UiModeManager::SIMPLE): void
    {
        $this->mode = app(UiModeManager::class)->normalize($mode);
    }

    public function render()
    {
        return view('livewire.filament.app.ui-mode-switcher');
    }
}
