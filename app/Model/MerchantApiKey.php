<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class MerchantApiKey extends Model
{
    protected $table = 'merchant_api_keys';

    protected $fillable = [
        'user_id',
        'name',
        'api_key',
        'api_secret_hash',
        'allowed_ips',
        'allowed_coins',
        'webhook_url',
        'webhook_secret',
        'is_active',
        'last_used_at',
    ];

    protected $casts = [
        'allowed_ips'   => 'array',
        'allowed_coins' => 'array',
        'is_active'     => 'boolean',
        'last_used_at'  => 'datetime',
    ];

    protected $hidden = ['api_secret_hash'];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function user()
    {
        return $this->belongsTo(\App\User::class);
    }

    public function orders()
    {
        return $this->hasMany(PaymentOrder::class, 'merchant_id');
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Generate a cryptographically random API key (public) and plain-text secret.
     * Returns ['api_key' => '...', 'plain_secret' => '...', 'api_secret_hash' => '...']
     */
    public static function generateCredentials(): array
    {
        $apiKey      = 'obx_' . bin2hex(random_bytes(20));   // 44 chars
        $plainSecret = bin2hex(random_bytes(32));             // 64 chars
        $secretHash  = hash('sha256', $plainSecret);         // Store deterministic SHA-256 hash for fast compare

        return [
            'api_key'         => $apiKey,
            'plain_secret'    => $plainSecret,
            'api_secret_hash' => $secretHash,
        ];
    }

    /**
     * Verify that the provided plain-text secret matches the stored hash.
     */
    public function verifySecret(string $plainSecret): bool
    {
        return hash_equals($this->api_secret_hash, hash('sha256', $plainSecret));
    }
}
