<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayoutRequest extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_CANCELLED = 'cancelled';

    public const ASSET_USDT_TRC20 = 'USDT_TRC20';
    public const ASSET_USDT_BEP20 = 'USDT_BEP20';
    public const ASSET_LTC = 'LTC';
    public const ASSET_BTC = 'BTC';

    protected $fillable = [
        'uuid',
        'user_id',
        'transaction_id',
        'asset',
        'crypto_address',
        'points_amount',
        'usd_amount',
        'crypto_amount',
        'status',
        'ip_address',
        'device_fingerprint',
        'admin_notes',
        'reviewed_by_id',
        'reviewed_at',
        'tx_hash',
    ];

    protected function casts(): array
    {
        return [
            'points_amount' => 'string',
            'usd_amount' => 'string',
            'crypto_amount' => 'string',
            'reviewed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_id');
    }
}
