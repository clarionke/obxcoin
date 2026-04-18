<?php

namespace Tests\Feature;

use App\Http\Services\AuthService;
use App\Model\Coin;
use App\Model\Wallet;
use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class RegistrationWalletProvisioningTest extends TestCase
{
    use DatabaseTransactions;

    /** @test */
    public function api_signup_creates_only_default_obxcoin_xpocket()
    {
        Coin::firstOrCreate(
            ['type' => DEFAULT_COIN_TYPE],
            [
                'name' => DEFAULT_COIN_TYPE,
                'status' => STATUS_ACTIVE,
                'is_withdrawal' => 1,
                'is_deposit' => 1,
                'is_buy' => 1,
                'is_sell' => 1,
            ]
        );

        Coin::firstOrCreate(
            ['type' => 'LTCT'],
            [
                'name' => 'Litecoin Test',
                'status' => STATUS_ACTIVE,
                'is_withdrawal' => 1,
                'is_deposit' => 1,
                'is_buy' => 1,
                'is_sell' => 1,
            ]
        );

        $email = 'signup-xpocket-' . uniqid() . '@example.com';

        $result = app(AuthService::class)->signUpProcess([
            'first_name' => 'Reg',
            'last_name' => 'User',
            'email' => $email,
            'password' => 'StrongPass123',
        ]);

        $this->assertTrue($result['success']);

        $user = User::where('email', $email)->first();
        $this->assertNotNull($user);

        $wallets = Wallet::where('user_id', $user->id)->get();
        $this->assertCount(1, $wallets);

        $wallet = $wallets->first();
        $this->assertSame(DEFAULT_COIN_TYPE, (string) $wallet->coin_type);
        $this->assertSame('OBXCoin XPocket', (string) $wallet->name);
        $this->assertEquals(1, (int) $wallet->is_primary);
    }
}
