<?php

namespace App;

use App\Model\AffiliationCode;
use App\Model\Coin;
use App\Model\Wallet;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Passport\HasApiTokens;
use Illuminate\Support\Facades\Log;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected static function booted()
    {
        static::created(function (self $user) {
            try {
                $defaultType = DEFAULT_COIN_TYPE;
                $defaultCoin = Coin::where('type', $defaultType)->first();

                $wallet = Wallet::where([
                    'user_id'   => $user->id,
                    'coin_type' => $defaultType,
                ])->orderByDesc('is_primary')->first();

                if ($wallet) {
                    if ((int) $wallet->is_primary !== 1) {
                        $wallet->is_primary = 1;
                        if (!$wallet->coin_id && $defaultCoin) {
                            $wallet->coin_id = $defaultCoin->id;
                        }
                        $wallet->save();
                    }
                    return;
                }

                Wallet::create([
                    'user_id'    => $user->id,
                    'name'       => 'OBX Wallet',
                    'status'     => STATUS_SUCCESS,
                    'is_primary' => STATUS_SUCCESS,
                    'balance'    => 0,
                    'coin_id'    => $defaultCoin?->id,
                    'coin_type'  => $defaultType,
                ]);
            } catch (\Throwable $e) {
                Log::warning('User wallet auto-create failed for user #' . $user->id . ': ' . $e->getMessage());
            }
        });
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'first_name','last_name', 'email','g2f_enabled', 'password','role','photo','phone','status',
        'is_verified','country_code','country','phone_verified','google2fa_secret','reset_code','gender', 'birth_date',
        'language', 'device_id', 'device_type', 'push_notification_status', 'email_notification_status',
        'bsc_wallet',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    public function affiliate()
    {
        return $this->hasOne(AffiliationCode::class);
    }
}
