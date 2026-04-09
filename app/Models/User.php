<?php

namespace App\Models;

use App\Notifications\VerifyEmailNotification;
use Filament\Models\Contracts\HasAvatar;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;

class User extends Authenticatable implements FilamentUser, HasAvatar, MustVerifyEmail
{
    use HasFactory;
    use Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'is_super_admin',
        'current_organization_id',
        'selected_site_id',
        'ui_mode',
        'avatar_url',
        'google_id',
        'email_verified_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_super_admin' => 'boolean',
            'selected_site_id' => 'integer',
            'ui_mode' => 'string',
        ];
    }

    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class)
            ->withPivot('role', 'permissions')
            ->withTimestamps();
    }

    public function currentOrganization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'current_organization_id');
    }

    public function canAccessPanel(Panel $panel): bool
    {
        if ($panel->getId() === 'admin') {
            return $this->is_super_admin;
        }

        if ($panel->getId() === 'app') {
            return ! $this->is_super_admin && $this->organizations()->exists();
        }

        return false;
    }

    public function organizationRole(int $organizationId): ?string
    {
        $organization = $this->organizations
            ->firstWhere('id', $organizationId);

        return $organization?->pivot?->role;
    }

    public function getFilamentAvatarUrl(): ?string
    {
        if (! $this->avatar_url) {
            return null;
        }

        if (str_starts_with($this->avatar_url, 'http://') || str_starts_with($this->avatar_url, 'https://')) {
            return $this->avatar_url;
        }

        return Storage::url($this->avatar_url);
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyEmailNotification);
    }

    public function publicAuthorProfileUrl(): ?string
    {
        return $this->hasPublicFounderIdentity() ? route('about') : null;
    }

    /**
     * @return list<array{label:string,url:string}>
     */
    public function publicSocialProfiles(): array
    {
        if (! $this->hasPublicFounderIdentity()) {
            return [];
        }

        return [
            [
                'label' => 'LinkedIn',
                'url' => 'https://www.linkedin.com/in/nikola-jocic/',
            ],
            [
                'label' => 'X',
                'url' => 'https://x.com/nikolajocicdev',
            ],
            [
                'label' => 'Upwork',
                'url' => 'https://www.upwork.com/freelancers/nikolajocic',
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public function publicSocialProfileUrls(): array
    {
        return array_map(
            static fn (array $profile): string => $profile['url'],
            $this->publicSocialProfiles()
        );
    }

    protected function hasPublicFounderIdentity(): bool
    {
        return in_array(mb_strtolower(trim((string) $this->name)), ['nikola jocic'], true)
            || in_array(mb_strtolower(trim((string) $this->email)), ['nikola@firephage.com'], true);
    }
}
