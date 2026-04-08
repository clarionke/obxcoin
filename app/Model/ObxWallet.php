<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

/**
 * ObxWallet — represents a blockchain wallet owned by a user.
 *
 * Each user can have multiple OBX wallets:
 *   • Exactly one is_primary wallet (auto-created on registration)
 *   • Additional wallets generated on demand
 *
 * Private keys are AES-256-GCM encrypted at rest via Laravel's Crypt facade.
 * The raw private key is NEVER exposed in API responses or logs.
 *
 * @property int    $id
 * @property int    $user_id
 * @property string $address            Ethereum address (lowercase, with 0x prefix)
 * @property string $label              User-defined label
 * @property bool   $is_primary
 * @property string $cached_balance     Last known on-chain OBX balance (18 dec precision)
 * @property \Carbon\Carbon|null $balance_updated_at
 */
class ObxWallet extends Model
{
    use HasFactory;

    protected $table = 'obx_wallets';

    protected $fillable = [
        'user_id',
        'address',
        'encrypted_private_key',
        'label',
        'is_primary',
        'cached_balance',
        'balance_updated_at',
    ];

    protected $casts = [
        'is_primary'         => 'boolean',
        'balance_updated_at' => 'datetime',
    ];

    /**
     * Fields never included in API responses or serialization.
     * The private key is only accessed programmatically via getDecryptedPrivateKey().
     */
    protected $hidden = [
        'encrypted_private_key',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function user()
    {
        return $this->belongsTo(\App\User::class);
    }

    // ─── Accessors ────────────────────────────────────────────────────────────

    /**
     * Decrypt and return the raw private key.
     * ONLY call this when about to sign a transaction — never log the result.
     */
    public function getDecryptedPrivateKey(): string
    {
        return Crypt::decryptString($this->encrypted_private_key);
    }
}
