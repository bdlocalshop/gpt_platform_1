<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Postback extends Model
{
    use HasFactory;

    public const STATUS_CREDITED = 'credited';
    public const STATUS_REVERSED = 'reversed';
    public const STATUS_DUPLICATE = 'duplicate';
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'provider',
        'external_transaction_id',
        'user_id',
        'transaction_id',
        'amount',
        'status',
        'is_reversal',
        'signature',
        'ip_address',
        'raw_payload',
    ];

    protected function casts(): array
    {
        return [
            'is_reversal' => 'boolean',
            'raw_payload' => 'array',
        ];
    }

    protected function amount(): Attribute
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

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }
}
