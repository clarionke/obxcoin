<?php

namespace Tests\Unit;

use Tests\TestCase;

/**
 * Unit tests for OBXStaking arithmetic.
 *
 * These tests run pure PHP arithmetic against the same constants and formulas
 * used in OBXStaking.sol so the business rules are verified in CI without
 * deploying any contract.
 *
 * ─── Contract spec ──────────────────────────────────────────────────────────
 *   Burn on stake   : burnBps / 10_000   (max 1000 bps = 10%)
 *   Net staked      : gross - burnStake
 *   APY reward      : net * apyBps * elapsedDays / (365 * 10_000)
 *   Burn on unstake : net * burnUnstakeBps / 10_000
 *   Total return    : net - burnUnstake + reward
 * ────────────────────────────────────────────────────────────────────────────
 */
class StakingMathTest extends TestCase
{
    // Mirror Solidity constants
    private const FEE_DENOM          = 10_000;
    private const MAX_BURN_BPS       = 1_000;   // 10%
    private const DAYS_PER_YEAR      = 365;

    // ─── Helpers ─────────────────────────────────────────────────────────────

    /** Burn on stake: mirrors contract burnOnStake() */
    private function calcBurnStake(float $gross, int $bps): float
    {
        return $gross * $bps / self::FEE_DENOM;
    }

    /** Net OBX held by contract after burn */
    private function calcNet(float $gross, int $bps): float
    {
        return $gross - $this->calcBurnStake($gross, $bps);
    }

    /** Pro-rata APY reward: mirrors contract _calcReward() */
    private function calcReward(float $net, int $apyBps, int $daysElapsed, int $durationDays = null): float
    {
        $elapsed = $durationDays !== null ? min($daysElapsed, $durationDays) : $daysElapsed;
        return $net * $apyBps * $elapsed / (self::DAYS_PER_YEAR * self::FEE_DENOM);
    }

    /** Burn on unstake */
    private function calcBurnUnstake(float $net, int $bps): float
    {
        return $net * $bps / self::FEE_DENOM;
    }

    /** Total tokens returned to staker on unstake */
    private function calcTotalReturn(float $net, int $burnUnstakeBps, int $apyBps, int $daysElapsed, int $durationDays): float
    {
        $reward     = $this->calcReward($net, $apyBps, $daysElapsed, $durationDays);
        $burnOut    = $this->calcBurnUnstake($net, $burnUnstakeBps);
        return $net - $burnOut + $reward;
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Burn on stake
    // ═══════════════════════════════════════════════════════════════════════

    /** @test */
    public function burn_on_stake_100_bps_of_100_obx_equals_1_obx()
    {
        $burned = $this->calcBurnStake(100, 100);
        $this->assertEquals(1.0, $burned);
    }

    /** @test */
    public function net_staked_after_100_bps_burn_is_99_obx()
    {
        $net = $this->calcNet(100, 100);
        $this->assertEquals(99.0, $net);
    }

    /** @test */
    public function burn_on_stake_with_0_bps_results_in_zero_burn()
    {
        $burned = $this->calcBurnStake(1000, 0);
        $this->assertEquals(0.0, $burned);
    }

    /** @test */
    public function burn_on_stake_at_max_1000_bps_equals_10_percent()
    {
        $burned = $this->calcBurnStake(100, self::MAX_BURN_BPS);
        $this->assertEquals(10.0, $burned);
    }

    /** @test */
    public function net_staked_at_max_burn_is_90_percent_of_gross()
    {
        $net = $this->calcNet(100, self::MAX_BURN_BPS);
        $this->assertEquals(90.0, $net);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // APY reward calculation
    // ═══════════════════════════════════════════════════════════════════════

    /** @test */
    public function apy_reward_1000_obx_500bps_30days_is_correct()
    {
        // 1000 * 500 * 30 / (365 * 10000) ≈ 4.109589...
        $reward = $this->calcReward(1000, 500, 30);
        $expected = 1000 * 500 * 30 / (self::DAYS_PER_YEAR * self::FEE_DENOM);
        $this->assertEqualsWithDelta($expected, $reward, 0.000001);
    }

    /** @test */
    public function pro_rata_apy_half_duration_gives_half_reward()
    {
        $fullReward = $this->calcReward(1000, 500, 30, 30);
        $halfReward = $this->calcReward(1000, 500, 15, 30);
        $this->assertEqualsWithDelta($fullReward / 2, $halfReward, 0.000001);
    }

    /** @test */
    public function elapsed_capped_at_duration_prevents_over_reward()
    {
        $rewardAtDuration  = $this->calcReward(1000, 500, 30, 30);
        $rewardAfterExpiry = $this->calcReward(1000, 500, 60, 30); // 60 days but dur=30
        $this->assertEquals($rewardAtDuration, $rewardAfterExpiry);
    }

    /** @test */
    public function zero_apy_gives_zero_reward()
    {
        $reward = $this->calcReward(1000, 0, 30, 30);
        $this->assertEquals(0.0, $reward);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Burn on unstake
    // ═══════════════════════════════════════════════════════════════════════

    /** @test */
    public function burn_on_unstake_200_bps_of_990_obx_equals_19_point_8()
    {
        $burned = $this->calcBurnUnstake(990, 200);
        $this->assertEqualsWithDelta(19.8, $burned, 0.000001);
    }

    /** @test */
    public function burn_on_unstake_with_0_bps_is_zero()
    {
        $burned = $this->calcBurnUnstake(990, 0);
        $this->assertEquals(0.0, $burned);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Total return
    // ═══════════════════════════════════════════════════════════════════════

    /** @test */
    public function total_return_is_net_minus_burn_unstake_plus_reward()
    {
        $net          = 990.0;
        $burnOutBps   = 200;    // 2%
        $apyBps       = 500;    // 5%
        $duration     = 30;

        $burnOut   = $this->calcBurnUnstake($net, $burnOutBps);     // 19.8
        $reward    = $this->calcReward($net, $apyBps, $duration, $duration);
        $expected  = $net - $burnOut + $reward;

        $actual = $this->calcTotalReturn($net, $burnOutBps, $apyBps, $duration, $duration);
        $this->assertEqualsWithDelta($expected, $actual, 0.000001);
    }

    /** @test */
    public function total_return_is_always_less_than_gross_when_burns_exceed_reward()
    {
        // Aggressive burns: 5% in + 5% out, low APY 1%, short hold 7d
        $gross      = 1000.0;
        $burnInBps  = 500;  // 5%
        $burnOutBps = 500;  // 5%
        $apyBps     = 100;  // 1%
        $duration   = 7;

        $net        = $this->calcNet($gross, $burnInBps);        // 950
        $returned   = $this->calcTotalReturn($net, $burnOutBps, $apyBps, $duration, $duration);

        $this->assertLessThan($gross, $returned);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Full round-trip
    // ═══════════════════════════════════════════════════════════════════════

    /** @test */
    public function full_round_trip_stake_100_1pct_burn_in_30d_5pct_apy_2pct_burn_out()
    {
        $gross        = 100.0;
        $burnInBps    = 100;   // 1%
        $burnOutBps   = 200;   // 2%
        $apyBps       = 500;   // 5%
        $durationDays = 30;

        $burnStake    = $this->calcBurnStake($gross, $burnInBps);     // 1.0
        $net          = $gross - $burnStake;                           // 99.0
        $reward       = $this->calcReward($net, $apyBps, $durationDays, $durationDays);
        // = 99 * 500 * 30 / (365 * 10000) ≈ 0.40685...
        $burnUnstake  = $this->calcBurnUnstake($net, $burnOutBps);    // 1.98
        $returned     = $net - $burnUnstake + $reward;

        $this->assertEqualsWithDelta(1.0, $burnStake, 0.000001);
        $this->assertEqualsWithDelta(99.0, $net, 0.000001);
        $this->assertEqualsWithDelta(1.98, $burnUnstake, 0.000001);
        $this->assertGreaterThan(0, $reward);
        $this->assertEqualsWithDelta($net - 1.98 + $reward, $returned, 0.000001);
    }
}
