<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserIpLog extends Model
{
    use HasFactory;

    public $timestamps = false; // Only created_at exists

    protected $fillable = [
        'user_id',
        'ip_address',
        'device_fingerprint',
        'user_agent',
        'user_agent_hash',
        'is_vpn',
        'is_proxy',
        'is_tor',
        'is_datacenter',
        'risk_score',
        'country_code',
        'city',
        'isp',
        'asn',
        'request_path',
        'action',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'is_vpn' => 'boolean',
            'is_proxy' => 'boolean',
            'is_tor' => 'boolean',
            'is_datacenter' => 'boolean',
            'risk_score' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
