<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class PaymentOrder extends Model
{
    protected $table = 'payment_orders';

    protected $fillable = [
        'uuid',
        'merchant_id',
        'merchant_order_id',
        'coin_type',
        'coin_id',
        'amount',
        'amount_received',
        'pay_address',
        'status',
        'metadata',
        'callback_url',
        'expires_at',
        'confirmed_at',
        'webhook_sent_at',
        'webhook_response',
        'transaction_hash',
    ];

    protected $casts = [
        'amount'          => 'decimal:8',
        'amount_received' => 'decimal:8',
        'metadata'        => 'array',
        'expires_at'      => 'datetime',
        'confirmed_at'    => 'datetime',
        'webhook_sent_at' => 'datetime',
    ];

    // ── Lifecycle constants ────────────────────────────────────────────────────

    const STATUS_PENDING     = 'pending';
    const STATUS_CONFIRMING  = 'confirming';
    const STATUS_COMPLETED   = 'completed';
    const STATUS_EXPIRED     = 'expired';
    const STATUS_UNDERPAID   = 'underpaid';

    // ── Boot ──────────────────────────────────────────────────────────────────

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (self $model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    // ── Relationships ─────────────────────────────────────────────────────────

    public function merchant()
    {
        return $this->belongsTo(MerchantApiKey::class, 'merchant_id');
    }

    public function coin()
    {
        return $this->belongsTo(Coin::class, 'coin_id');
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeExpired($query)
    {
        return $query->where('status', self::STATUS_PENDING)
                     ->where('expires_at', '<', now());
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast()
            && in_array($this->status, [self::STATUS_PENDING, self::STATUS_CONFIRMING]);
    }

    public function checkoutUrl(): string
    {
        return url('/pay/' . $this->uuid);
    }

    /**
     * Build the public JSON representation returned to the merchant API.
     */
    public function toApiArray(): array
    {
        return [
            'id'               => $this->uuid,
            'merchant_order_id' => $this->merchant_order_id,
            'coin_type'        => $this->coin_type,
            'amount'           => (string) $this->amount,
            'amount_received'  => (string) $this->amount_received,
            'pay_address'      => $this->pay_address,
            'status'           => $this->status,
            'checkout_url'     => $this->checkoutUrl(),
            'expires_at'       => $this->expires_at?->toISOString(),
            'confirmed_at'     => $this->confirmed_at?->toISOString(),
            'metadata'         => $this->metadata,
            'created_at'       => $this->created_at->toISOString(),
        ];
    }
}
