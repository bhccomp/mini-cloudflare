<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiteFirewallRule extends Model
{
    public const TYPE_IP = 'ip';

    public const TYPE_CIDR = 'cidr';

    public const TYPE_COUNTRY = 'country';

    public const TYPE_CONTINENT = 'continent';

    public const ACTION_BLOCK = 'block';

    public const ACTION_ALLOW = 'allow';

    public const ACTION_CHALLENGE = 'challenge';

    public const MODE_STAGED = 'staged';

    public const MODE_ENFORCED = 'enforced';

    public const STATUS_PENDING = 'pending';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_REMOVED = 'removed';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'site_id',
        'created_by_user_id',
        'provider',
        'provider_rule_id',
        'rule_type',
        'target',
        'action',
        'mode',
        'status',
        'expires_at',
        'activated_at',
        'expired_at',
        'note',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'expires_at' => 'datetime',
            'activated_at' => 'datetime',
            'expired_at' => 'datetime',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public static function actionOptions(): array
    {
        return [
            self::ACTION_BLOCK => 'Block',
            self::ACTION_ALLOW => 'Allow',
            self::ACTION_CHALLENGE => 'Challenge',
        ];
    }

    public static function typeOptions(): array
    {
        return [
            self::TYPE_IP => 'Single IP',
            self::TYPE_CIDR => 'CIDR Range',
            self::TYPE_COUNTRY => 'Country',
            self::TYPE_CONTINENT => 'Continent',
        ];
    }
}
