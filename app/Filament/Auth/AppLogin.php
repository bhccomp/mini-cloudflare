<?php

namespace App\Filament\Auth;

use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use App\Services\DemoModeService;
use Filament\Auth\Pages\Login;
use Filament\Facades\Filament;
use Filament\Actions\Action;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\HtmlString;

class AppLogin extends Login
{
    public function mount(): void
    {
        if (Filament::auth()->check()) {
            redirect()->intended(Filament::getUrl());
        }

        $state = [];
        $demoMode = app(DemoModeService::class);

        if ($demoMode->active(request())) {
            $this->ensureDemoCaptchaChallenge();

            $state = [
                'email' => $demoMode->demoEmail(),
                'password' => $demoMode->demoPassword(),
            ];
        }

        $this->form->fill($state);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getEmailFormComponent(),
                $this->getPasswordFormComponent(),
                $this->getDemoCaptchaFormComponent(),
                $this->getRememberFormComponent(),
            ]);
    }

    public function authenticate(): ?LoginResponse
    {
        if (app(DemoModeService::class)->active(request())) {
            $this->ensureDemoCaptchaChallenge();
            $this->validateDemoCaptcha();
        }

        return parent::authenticate();
    }

    public function getSubheading(): string | \Illuminate\Contracts\Support\Htmlable | null
    {
        if (filled($this->userUndertakingMultiFactorAuthentication)) {
            return parent::getSubheading();
        }

        return new HtmlString('Don&apos;t have an account? <a href="'.e(url('/register')).'" class="fi-link fi-size-sm">Register here</a>');
    }

    protected function getFormActions(): array
    {
        $actions = [
            $this->getAuthenticateFormAction(),
        ];

        if (
            filled(config('services.google.client_id'))
            && filled(config('services.google.client_secret'))
            && filled(config('services.google.redirect'))
        ) {
            $actions[] = Action::make('google')
                ->label('Continue with Google')
                ->color('gray')
                ->url(route('auth.google.redirect'));
        }

        return $actions;
    }

    protected function getDemoCaptchaFormComponent(): Component
    {
        return TextInput::make('demo_captcha')
            ->label('Security check')
            ->placeholder('Enter the answer')
            ->numeric()
            ->required(fn (): bool => app(DemoModeService::class)->active(request()))
            ->visible(fn (): bool => app(DemoModeService::class)->active(request()))
            ->helperText(function (): string {
                $challenge = session('demo_login_challenge', []);
                $first = (int) ($challenge['first'] ?? 0);
                $second = (int) ($challenge['second'] ?? 0);

                return "What is {$first} + {$second}?";
            });
    }

    protected function ensureDemoCaptchaChallenge(): void
    {
        if (session()->has('demo_login_challenge.answer')) {
            return;
        }

        $first = random_int(2, 9);
        $second = random_int(1, 9);

        session([
            'demo_login_challenge' => [
                'first' => $first,
                'second' => $second,
                'answer' => $first + $second,
            ],
        ]);
    }

    protected function refreshDemoCaptchaChallenge(): void
    {
        session()->forget('demo_login_challenge');
        $this->ensureDemoCaptchaChallenge();
    }

    protected function validateDemoCaptcha(): void
    {
        $state = $this->form->getState();
        $expected = (int) data_get(session('demo_login_challenge', []), 'answer', -1);
        $provided = (int) ($state['demo_captcha'] ?? -2);

        if ($expected === $provided) {
            return;
        }

        $this->refreshDemoCaptchaChallenge();

        throw ValidationException::withMessages([
            'data.demo_captcha' => 'Please solve the security check and try again.',
        ]);
    }
}
