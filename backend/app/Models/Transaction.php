<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Transaction extends Model
{
    use HasFactory;

    public const TYPE_OFFER_REWARD = 'offer_reward';
    public const TYPE_PAYOUT_LOCK = 'payout_lock';
    public const TYPE_PAYOUT_COMPLETE = 'payout_complete';
    public const TYPE_PAYOUT_REJECT = 'payout_reject';
    public const TYPE_REVERSAL = 'reversal';
    public const TYPE_SIGNUP_BONUS = 'signup_bonus';
    public const TYPE_REFERRAL_BONUS = 'referral_bonus';
    public const TYPE_ADJUSTMENT = 'adjustment';

    public const STATUS_PENDING = 'pending';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_REVERSED = 'reversed';

    protected $fillable = [
        'uuid',
        'type',
        'reference_type',
        'reference_id',
        'status',
        'description',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class);
    }

    public function payoutRequests(): HasMany
    {
        return $this->hasMany(PayoutRequest::class);
    }
}
