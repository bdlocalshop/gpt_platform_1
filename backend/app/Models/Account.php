<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Account extends Model
{
    use HasFactory;

    public const TYPE_USER_AVAILABLE = 'user_available';
    public const TYPE_USER_LOCKED = 'user_locked';
    public const TYPE_PLATFORM_LIABILITY = 'platform_liability';
    public const TYPE_PLATFORM_REVENUE = 'platform_revenue';
    public const TYPE_PLATFORM_RESERVE = 'platform_reserve';

    protected $fillable = [
        'code',
        'user_id',
        'type',
        'currency',
        'balance',
        'status',
    ];

    /**
     * Strictly enforce 4-decimal precision representation.
     */
    protected function balance(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => number_format((float) ($value ?? 0), 4, '.', ''),
            set: fn ($value) => number_format((float) ($value ?? 0), 4, '.', ''),
        );
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class);
    }

    public function isUserAccount(): bool
    {
        return in_array($this->type, [self::TYPE_USER_AVAILABLE, self::TYPE_USER_LOCKED]);
    }
}
