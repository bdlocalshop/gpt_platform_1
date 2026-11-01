<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

class LedgerEntry extends Model
{
    use HasFactory;

    public $timestamps = false; // Only created_at exists

    public const DIRECTION_DEBIT = 'debit';
    public const DIRECTION_CREDIT = 'credit';

    protected $fillable = [
        'transaction_id',
        'account_id',
        'direction',
        'amount',
        'balance_after',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    protected function amount(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => number_format((float) ($value ?? 0), 4, '.', ''),
            set: fn ($value) => number_format((float) ($value ?? 0), 4, '.', ''),
        );
    }

    protected function balanceAfter(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => number_format((float) ($value ?? 0), 4, '.', ''),
            set: fn ($value) => number_format((float) ($value ?? 0), 4, '.', ''),
        );
    }

    /**
     * Invariant: Ledger entries are strictly immutable.
     */
    protected static function booted(): void
    {
        static::updating(function () {
            throw new RuntimeException('Direct modification of ledger entries is strictly prohibited.');
        });

        static::deleting(function () {
            throw new RuntimeException('Direct deletion of ledger entries is strictly prohibited.');
        });
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
