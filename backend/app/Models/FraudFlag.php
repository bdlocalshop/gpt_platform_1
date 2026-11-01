<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FraudFlag extends Model
{
    use HasFactory;

    public const TYPE_MULTI_ACCOUNT_FP = 'multi_account_fingerprint';
    public const TYPE_MULTI_ACCOUNT_IP = 'multi_account_ip';
    public const TYPE_VPN_DETECTED = 'vpn_detected';
    public const TYPE_REUSED_CRYPTO_ADDRESS = 'reused_crypto_address';
    public const TYPE_NEGATIVE_BALANCE_HOLD = 'negative_balance_hold';

    public const SEVERITY_LOW = 'low';
    public const SEVERITY_MEDIUM = 'medium';
    public const SEVERITY_HIGH = 'high';
    public const SEVERITY_CRITICAL = 'critical';

    protected $fillable = [
        'user_id',
        'flag_type',
        'severity',
        'details',
        'is_resolved',
        'resolved_by_id',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'details' => 'array',
            'is_resolved' => 'boolean',
            'resolved_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by_id');
    }
}
