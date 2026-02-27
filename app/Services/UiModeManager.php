<?php

namespace App\Services;

use App\Models\User;

class UiModeManager
{
    public const SIMPLE = 'simple';

    public const PRO = 'pro';

    /**
     * @return array<int, string>
     */
    public function modes(): array
    {
        return [self::SIMPLE, self::PRO];
    }

    public function sessionKey(): string
    {
        return (string) config('ui.session_key', 'app.ui_mode');
    }

    public function defaultMode(): string
    {
        $configured = strtolower(trim((string) config('ui.default_mode', self::SIMPLE)));

        return in_array($configured, $this->modes(), true) ? $configured : self::SIMPLE;
    }

    public function current(?User $user = null): string
    {
        $session = session($this->sessionKey());
        if (is_string($session) && in_array($session, $this->modes(), true)) {
            return $session;
        }

        $user ??= auth()->user();
        if ($user instanceof User) {
            $mode = $this->normalize((string) ($user->ui_mode ?: $this->defaultMode()));
            session([$this->sessionKey() => $mode]);

            return $mode;
        }

        return $this->defaultMode();
    }

    public function setMode(User $user, string $mode): string
    {
        $normalized = $this->normalize($mode);

        if ((string) $user->ui_mode !== $normalized) {
            $user->forceFill(['ui_mode' => $normalized])->save();
        }

        session([$this->sessionKey() => $normalized]);

        return $normalized;
    }

    public function isPro(?User $user = null): bool
    {
        return $this->current($user) === self::PRO;
    }

    public function isSimple(?User $user = null): bool
    {
        return $this->current($user) === self::SIMPLE;
    }

    public function normalize(string $mode): string
    {
        $mode = strtolower(trim($mode));

        return in_array($mode, $this->modes(), true) ? $mode : $this->defaultMode();
    }
}
