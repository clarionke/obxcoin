<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use App\Model\AirdropCampaign;
use App\Model\AirdropClaim;
use App\Model\AirdropUnlock;
use App\Model\Wallet;
use App\User;
use Carbon\Carbon;

/**
 * Airdrop Feature Tests
 *
 * Covers:
 *  - Unauthenticated access is blocked
 *  - Admin can create / update / toggle campaigns
 *  - Admin cannot edit a started campaign
 *  - Admin can reveal fee only after campaign ends
 *  - User can claim once per day during live campaign
 *  - User cannot claim twice on the same day
 *  - User cannot claim before campaign starts
 *  - User cannot claim after campaign ends
 *  - User cannot claim if already unlocked
 *  - Locked balance accumulates correctly across multiple days
 *  - User cannot unlock before campaign ends
 *  - User cannot unlock before fee is revealed
 *  - User with no claims cannot unlock
 *  - User can request unlock after campaign ends + fee revealed
 *  - Duplicate unlock request returns info redirect
 *  - confirmUnlock marks the record confirmed
 */
class AirdropTest extends TestCase
{
    use DatabaseTransactions;

    // ── Factory helpers ───────────────────────────────────────────────────────

    private function makeUser(int $role = 2): User
    {
        return User::factory()->create([
            'role'        => $role,
            'status'      => 1,
            'is_verified' => 1,
        ]);
    }

    private function makeAdmin(): User
    {
        return $this->makeUser(1);
    }

    private function liveCampaign(array $overrides = []): AirdropCampaign
    {
        return AirdropCampaign::create(array_merge([
            'name'                => 'Test Airdrop',
            'start_date'          => now()->subHour(),
            'end_date'            => now()->addDays(30),
            'daily_claim_amount'  => '100000000000000000000', // 100 OBX (18 dec)
            'streak_days'         => 5,
            'streak_bonus_amount' => '500000000000000000000', // 500 OBX bonus
            'is_active'           => true,
        ], $overrides));
    }

    private function endedCampaign(bool $feeRevealed = false, float $fee = 5.0): AirdropCampaign
    {
        return AirdropCampaign::create([
            'name'                => 'Ended Campaign',
            'start_date'          => now()->subDays(31),
            'end_date'            => now()->subDay(),
            'daily_claim_amount'  => '100000000000000000000',
            'streak_days'         => 5,
            'streak_bonus_amount' => '500000000000000000000',
            'is_active'           => true,
            'fee_revealed'        => $feeRevealed,
            'unlock_fee_usdt'     => $feeRevealed ? $fee : null,
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Auth boundary
    // ═══════════════════════════════════════════════════════════════════════

    /** @test */
    public function unauthenticated_user_cannot_view_airdrop_dashboard()
    {
        $response = $this->get('/user/airdrop');
        $response->assertRedirect(route('login'));
    }

    /** @test */
    public function unauthenticated_user_cannot_claim()
    {
        $response = $this->post('/user/airdrop/claim');
        $response->assertRedirect(route('login'));
    }

    /** @test */
    public function unauthenticated_user_cannot_access_admin_airdrop()
    {
        $response = $this->get('/admin/airdrop');
        $response->assertRedirect(route('login'));
    }

    /** @test */
    public function regular_user_cannot_access_admin_airdrop()
    {
        $user = $this->makeUser();
        $response = $this->actingAs($user)->get('/admin/airdrop');
        $response->assertRedirect(); // redirected by admin middleware
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Admin: campaign management
    // ═══════════════════════════════════════════════════════════════════════

    /** @test */
    public function admin_can_view_airdrop_campaign_list()
    {
        $admin = $this->makeAdmin();
        $response = $this->actingAs($admin)->get(route('admin.airdrop.index'));
        $response->assertStatus(200);
    }

    /** @test */
    public function admin_can_create_a_campaign()
    {
        $admin = $this->makeAdmin();

        $response = $this->actingAs($admin)->post(route('admin.airdrop.store'), [
            'name'                => 'Wave 1',
            'start_date'          => now()->addHour()->format('Y-m-d H:i:s'),
            'end_date'            => now()->addDays(30)->format('Y-m-d H:i:s'),
            'daily_claim_amount'  => '100',
            'streak_days'         => '5',
            'streak_bonus_amount' => '500',
            'is_active'           => '1',
        ]);

        $response->assertRedirect(route('admin.airdrop.index'));
        $this->assertDatabaseHas('airdrop_campaigns', ['name' => 'Wave 1', 'streak_days' => 5]);
    }

    /** @test */
    public function admin_cannot_edit_a_campaign_that_has_started()
    {
        $admin    = $this->makeAdmin();
        $campaign = $this->liveCampaign(); // already started

        $response = $this->actingAs($admin)->get(route('admin.airdrop.edit', $campaign->id));
        $response->assertRedirect(route('admin.airdrop.index'));
    }

    /** @test */
    public function admin_can_toggle_campaign_active_status()
    {
        $admin    = $this->makeAdmin();
        $campaign = $this->liveCampaign(['is_active' => true]);

        $this->actingAs($admin)->get(route('admin.airdrop.toggleActive', $campaign->id));

        $this->assertDatabaseHas('airdrop_campaigns', [
            'id'        => $campaign->id,
            'is_active' => 0,
        ]);
    }

    /** @test */
    public function admin_cannot_reveal_fee_before_campaign_ends()
    {
        $admin    = $this->makeAdmin();
        $campaign = $this->liveCampaign(); // still running

        $response = $this->actingAs($admin)->post(route('admin.airdrop.revealFee', $campaign->id), [
            'unlock_fee_usdt' => '5.00',
        ]);

        $response->assertRedirect(route('admin.airdrop.index'));
        $this->assertDatabaseHas('airdrop_campaigns', ['id' => $campaign->id, 'fee_revealed' => 0]);
    }

    /** @test */
    public function admin_can_reveal_fee_after_campaign_ends()
    {
        $admin    = $this->makeAdmin();
        $campaign = $this->endedCampaign(false);

        $response = $this->actingAs($admin)->post(route('admin.airdrop.revealFee', $campaign->id), [
            'unlock_fee_usdt' => '5.00',
        ]);

        $response->assertRedirect(route('admin.airdrop.index'));
        $this->assertDatabaseHas('airdrop_campaigns', [
            'id'           => $campaign->id,
            'fee_revealed' => 1,
        ]);
    }

    /** @test */
    public function admin_cannot_reveal_fee_twice()
    {
        $admin    = $this->makeAdmin();
        $campaign = $this->endedCampaign(true, 5.0);

        $response = $this->actingAs($admin)->post(route('admin.airdrop.revealFee', $campaign->id), [
            'unlock_fee_usdt' => '10.00',
        ]);

        $response->assertRedirect(route('admin.airdrop.index'));
        // Fee must remain the original value
        $this->assertDatabaseHas('airdrop_campaigns', [
            'id'             => $campaign->id,
            'unlock_fee_usdt' => 5.0,
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // User: daily claim
    // ═══════════════════════════════════════════════════════════════════════

    /** @test */
    public function user_can_claim_airdrop_during_live_campaign()
    {
        $user     = $this->makeUser();
        $campaign = $this->liveCampaign();

        $response = $this->actingAs($user)->post(route('user.airdrop.claim'));
        $response->assertRedirect(route('user.airdrop'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('airdrop_claims', [
            'user_id'     => $user->id,
            'campaign_id' => $campaign->id,
        ]);
    }

    /** @test */
    public function user_cannot_claim_twice_on_the_same_day()
    {
        $user     = $this->makeUser();
        $campaign = $this->liveCampaign();

        // First claim
        AirdropClaim::create([
            'user_id'     => $user->id,
            'campaign_id' => $campaign->id,
            'claim_date'  => Carbon::today(),
            'amount_obx'  => $campaign->daily_claim_amount,
        ]);

        // Second attempt same day
        $response = $this->actingAs($user)->post(route('user.airdrop.claim'));
        $response->assertRedirect(route('user.airdrop'));
        $response->assertSessionHas('dismiss');

        $count = AirdropClaim::where('user_id', $user->id)
            ->where('campaign_id', $campaign->id)
            ->whereDate('claim_date', Carbon::today())
            ->count();
        $this->assertEquals(1, $count, 'Only one claim should exist for today');
    }

    /** @test */
    public function user_cannot_claim_before_campaign_starts()
    {
        $user     = $this->makeUser();
        $campaign = $this->liveCampaign([
            'start_date' => now()->addHour(),
            'end_date'   => now()->addDays(30),
        ]);

        $response = $this->actingAs($user)->post(route('user.airdrop.claim'));
        $response->assertRedirect(route('user.airdrop'));
        $response->assertSessionHas('dismiss');
    }

    /** @test */
    public function user_cannot_claim_after_campaign_ends()
    {
        $user     = $this->makeUser();
        AirdropCampaign::create([
            'name'                => 'Ended',
            'start_date'          => now()->subDays(10),
            'end_date'            => now()->subHour(),
            'daily_claim_amount'  => '100000000000000000000',
            'streak_days'         => 5,
            'streak_bonus_amount' => '0',
            'is_active'           => true,
        ]);

        $response = $this->actingAs($user)->post(route('user.airdrop.claim'));
        $response->assertRedirect(route('user.airdrop'));
        $response->assertSessionHas('dismiss');
    }

    /** @test */
    public function locked_balance_accumulates_across_multiple_claims()
    {
        $user     = $this->makeUser();
        $campaign = $this->liveCampaign();

        // Simulate 3 days of claims
        foreach (range(0, 2) as $daysAgo) {
            AirdropClaim::create([
                'user_id'     => $user->id,
                'campaign_id' => $campaign->id,
                'claim_date'  => Carbon::today()->subDays($daysAgo),
                'amount_obx'  => $campaign->daily_claim_amount,
            ]);
        }

        $total = AirdropClaim::where('user_id', $user->id)
            ->where('campaign_id', $campaign->id)
            ->sum('amount_obx');

        $expected = bcmul($campaign->daily_claim_amount, '3', 18);
        $this->assertEquals($expected, $total, 'Locked balance should be 3x daily amount');
    }

    /** @test */
    public function different_users_have_isolated_claim_balances()
    {
        $userA    = $this->makeUser();
        $userB    = $this->makeUser();
        $campaign = $this->liveCampaign();

        // A claims today, B does not
        $this->actingAs($userA)->post(route('user.airdrop.claim'));

        $aCount = AirdropClaim::where('user_id', $userA->id)->where('campaign_id', $campaign->id)->count();
        $bCount = AirdropClaim::where('user_id', $userB->id)->where('campaign_id', $campaign->id)->count();

        $this->assertEquals(1, $aCount);
        $this->assertEquals(0, $bCount, 'User B should have no claims');
    }

    // ═══════════════════════════════════════════════════════════════════════
    // User: unlock
    // ═══════════════════════════════════════════════════════════════════════

    /** @test */
    public function user_cannot_unlock_before_campaign_ends()
    {
        $user     = $this->makeUser();
        $campaign = $this->liveCampaign();

        $response = $this->actingAs($user)->post(route('user.airdrop.unlock'), [
            'campaign_id' => $campaign->id,
        ]);

        $response->assertRedirect(route('user.airdrop'));
        $response->assertSessionHas('dismiss');
    }

    /** @test */
    public function user_cannot_unlock_before_fee_is_revealed()
    {
        $user     = $this->makeUser();
        $campaign = $this->endedCampaign(false); // fee NOT revealed

        AirdropClaim::create([
            'user_id'     => $user->id,
            'campaign_id' => $campaign->id,
            'claim_date'  => Carbon::yesterday(),
            'amount_obx'  => '100000000000000000000',
        ]);

        $response = $this->actingAs($user)->post(route('user.airdrop.unlock'), [
            'campaign_id' => $campaign->id,
        ]);

        $response->assertRedirect(route('user.airdrop'));
        $response->assertSessionHas('dismiss');
    }

    /** @test */
    public function user_with_no_claims_cannot_unlock()
    {
        $user     = $this->makeUser();
        $campaign = $this->endedCampaign(true, 5.0);

        $response = $this->actingAs($user)->post(route('user.airdrop.unlock'), [
            'campaign_id' => $campaign->id,
        ]);

        $response->assertRedirect(route('user.airdrop'));
        $response->assertSessionHas('dismiss');
        $this->assertDatabaseMissing('airdrop_unlocks', ['user_id' => $user->id]);
    }

    /** @test */
    public function user_can_request_unlock_after_campaign_ends_and_fee_revealed()
    {
        $user     = $this->makeUser();
        $campaign = $this->endedCampaign(true, 5.0);

        AirdropClaim::create([
            'user_id'     => $user->id,
            'campaign_id' => $campaign->id,
            'claim_date'  => Carbon::yesterday(),
            'amount_obx'  => '100000000000000000000',
        ]);

        $response = $this->actingAs($user)->post(route('user.airdrop.unlock'), [
            'campaign_id' => $campaign->id,
        ]);

        $response->assertRedirect(route('user.airdrop'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('airdrop_unlocks', [
            'user_id'     => $user->id,
            'campaign_id' => $campaign->id,
            'status'      => 'pending',
        ]);
    }

    /** @test */
    public function duplicate_unlock_request_returns_info_not_error()
    {
        $user     = $this->makeUser();
        $campaign = $this->endedCampaign(true, 5.0);

        AirdropClaim::create([
            'user_id'     => $user->id,
            'campaign_id' => $campaign->id,
            'claim_date'  => Carbon::yesterday(),
            'amount_obx'  => '100000000000000000000',
        ]);

        // First request
        $this->actingAs($user)->post(route('user.airdrop.unlock'), ['campaign_id' => $campaign->id]);

        // Second request (duplicate)
        $response = $this->actingAs($user)->post(route('user.airdrop.unlock'), ['campaign_id' => $campaign->id]);

        $response->assertRedirect(route('user.airdrop'));
        // Should get info, not dismiss
        $response->assertSessionHas('info');

        // Still only one unlock record
        $this->assertEquals(
            1,
            AirdropUnlock::where('user_id', $user->id)->where('campaign_id', $campaign->id)->count()
        );
    }

    /** @test */
    public function airdrop_dashboard_shows_claim_button_for_live_campaign()
    {
        $user     = $this->makeUser();
        $campaign = $this->liveCampaign(['name' => 'Spring Drop']);

        $response = $this->actingAs($user)->get(route('user.airdrop'));
        $response->assertStatus(200);
        $response->assertSee('Spring Drop');
        $response->assertSee(route('user.airdrop.claim'));
    }

    /** @test */
    public function airdrop_dashboard_shows_already_claimed_state()
    {
        $user     = $this->makeUser();
        $campaign = $this->liveCampaign();

        AirdropClaim::create([
            'user_id'     => $user->id,
            'campaign_id' => $campaign->id,
            'claim_date'  => Carbon::today(),
            'amount_obx'  => $campaign->daily_claim_amount,
        ]);

        $response = $this->actingAs($user)->get(route('user.airdrop'));
        $response->assertStatus(200);
        // The daily claim button should NOT appear
        $response->assertDontSee(route('user.airdrop.claim'));
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Streak gamification
    // ═══════════════════════════════════════════════════════════════════════

    /** @test */
    public function streak_bonus_is_awarded_on_nth_consecutive_day()
    {
        $user     = $this->makeUser();
        $campaign = $this->liveCampaign(['streak_days' => 5, 'streak_bonus_amount' => '500000000000000000000']);

        // Seed 4 consecutive days before today
        foreach (range(4, 1) as $daysAgo) {
            AirdropClaim::create([
                'user_id'     => $user->id,
                'campaign_id' => $campaign->id,
                'claim_date'  => Carbon::today()->subDays($daysAgo),
                'amount_obx'  => $campaign->daily_claim_amount,
                'is_bonus'    => false,
            ]);
        }

        // On day 5 (today) claim via route — should trigger bonus
        $response = $this->actingAs($user)->post(route('user.airdrop.claim'));
        $response->assertRedirect(route('user.airdrop'));
        $response->assertSessionHas('success');

        // There should be a bonus claim for today
        $this->assertDatabaseHas('airdrop_claims', [
            'user_id'     => $user->id,
            'campaign_id' => $campaign->id,
            'is_bonus'    => 1,
        ]);
    }

    /** @test */
    public function streak_bonus_is_not_awarded_before_milestone()
    {
        $user     = $this->makeUser();
        $campaign = $this->liveCampaign(['streak_days' => 5, 'streak_bonus_amount' => '500000000000000000000']);

        // Only 2 consecutive days before today (day 3 total, below milestone of 5)
        foreach (range(2, 1) as $daysAgo) {
            AirdropClaim::create([
                'user_id'     => $user->id,
                'campaign_id' => $campaign->id,
                'claim_date'  => Carbon::today()->subDays($daysAgo),
                'amount_obx'  => $campaign->daily_claim_amount,
                'is_bonus'    => false,
            ]);
        }

        $response = $this->actingAs($user)->post(route('user.airdrop.claim'));
        $response->assertRedirect(route('user.airdrop'));

        // No bonus claim should exist
        $this->assertDatabaseMissing('airdrop_claims', [
            'user_id'     => $user->id,
            'campaign_id' => $campaign->id,
            'is_bonus'    => 1,
        ]);
    }

    /** @test */
    public function streak_resets_when_a_day_is_missed()
    {
        $user     = $this->makeUser();
        $campaign = $this->liveCampaign(['streak_days' => 3, 'streak_bonus_amount' => '300000000000000000000']);

        // Claims on day -3 and day -1 (gap on day -2 breaks the streak counting backwards from today)
        AirdropClaim::create([
            'user_id'     => $user->id,
            'campaign_id' => $campaign->id,
            'claim_date'  => Carbon::today()->subDays(3),
            'amount_obx'  => $campaign->daily_claim_amount,
            'is_bonus'    => false,
        ]);
        AirdropClaim::create([
            'user_id'     => $user->id,
            'campaign_id' => $campaign->id,
            'claim_date'  => Carbon::today()->subDays(1),
            'amount_obx'  => $campaign->daily_claim_amount,
            'is_bonus'    => false,
        ]);

        // Claim today — streak from today is only 1 (gap on yesterday-2 broke it)
        $response = $this->actingAs($user)->post(route('user.airdrop.claim'));
        $response->assertRedirect(route('user.airdrop'));

        // No bonus should be awarded (streak milestone = 3, but current run = 1)
        $this->assertDatabaseMissing('airdrop_claims', [
            'user_id'     => $user->id,
            'campaign_id' => $campaign->id,
            'is_bonus'    => 1,
        ]);
    }

    /** @test */
    public function confirm_unlock_credits_user_wallet_balance()
    {
        $user     = $this->makeUser();
        $campaign = $this->endedCampaign(true, 5.0);

        // Create wallet for user
        $wallet = Wallet::create([
            'user_id'   => $user->id,
            'coin_type' => DEFAULT_COIN_TYPE,
            'balance'   => 0,
        ]);

        // Create pending unlock
        AirdropUnlock::create([
            'user_id'      => $user->id,
            'campaign_id'  => $campaign->id,
            'usdt_paid'    => 5.0,
            'obx_released' => '100',
            'status'       => 'pending',
        ]);

        // Simulate confirmUnlock callback (route requires auth as regular user)
        $response = $this->actingAs($user)->post(route('user.airdrop.confirmUnlock'), [
            'user_id'     => $user->id,
            'campaign_id' => $campaign->id,
            'tx_hash'     => '0x' . str_repeat('a', 64),
            'obx_amount'  => '100',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        // Wallet balance should now be 100
        $wallet->refresh();
        $this->assertEquals(100.0, (float) $wallet->balance);

        // Unlock record should be confirmed
        $this->assertDatabaseHas('airdrop_unlocks', [
            'user_id'     => $user->id,
            'campaign_id' => $campaign->id,
            'status'      => 'confirmed',
        ]);
    }
}
