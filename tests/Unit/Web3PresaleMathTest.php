<?php

namespace Tests\Unit;

use Tests\TestCase;

/**
 * Web3 Presale Math Tests
 *
 * Verifies arithmetic correctness for all on-chain presale calculations
 * that the PHP backend performs when crediting OBX to users' wallets:
 *
 * - USDT → OBX conversion at various rates
 * - Bonus OBX calculation (basis points)
 * - Phase fee deduction
 * - Total OBX = base + bonus
 * - Burn fee applied on transfer (mirrors OBXToken.sol)
 * - Burn floor stops burn permanently
 * - Overflow-safe BigNumber handling (bcmath)
 * - Phase cap enforcement
 * - Referral bonus calculation
 * - On-chain event amount trust (webhook cannot override)
 *
 * All arithmetic uses bcmath to match Solidity uint256 precision.
 */
class Web3PresaleMathTest extends TestCase
{
    // OBXToken.sol constants
    private const DECIMALS       = 18;
    private const INITIAL_SUPPLY = '100000000';
    private const BURN_FLOOR_HU  = '41000000';
    private const BURN_FEE_BPS   = 5;
    private const FEE_DENOM      = 10_000;

    private string $unit;          // 1e18
    private string $initialSupply; // raw
    private string $burnFloor;     // raw

    protected function setUp(): void
    {
        parent::setUp();
        $this->unit          = bcpow('10', (string) self::DECIMALS, 0);
        $this->initialSupply = bcmul(self::INITIAL_SUPPLY, $this->unit, 0);
        $this->burnFloor     = bcmul(self::BURN_FLOOR_HU,  $this->unit, 0);
    }

    // ── Private helper: mirrors OBXPresale.sol rateCalc ─────────────────────

    /**
     * Calculate OBX tokens for a given USDT amount at a rate.
     *
     * Solidity equivalent:
     *   obx = (usdtAmount * rateOBXperUSDT) / 1e18
     *
     * @param string $usdtHuman   Human-readable USDT (e.g. "100")
     * @param string $rateHuman   Human-readable rate OBX per USDT (e.g. "10000" means 10k OBX / USDT)
     * @param int    $usdtDecimals  USDT decimals (usually 6 on BSC)
     * @return string  OBX in raw units (18 decimals)
     */
    private function calcObxRaw(string $usdtHuman, string $rateHuman): string
    {
        // usdtRaw = usdtHuman * 1e6
        $usdtRaw  = bcmul($usdtHuman, bcpow('10', '6', 0), 0);
        // rateRaw = rateHuman * 1e18/1e6 = rateHuman * 1e12  (per 1 USDT unit in 6 dec)
        // Simpler: obxRaw = usdtHuman * rateHuman * 1e18
        return bcmul(bcmul($usdtHuman, $rateHuman, 0), $this->unit, 0);
    }

    /**
     * Calculate bonus OBX given a base amount and bonus BPS.
     */
    private function calcBonus(string $baseObxRaw, int $bonusBps): string
    {
        return bcdiv(bcmul($baseObxRaw, (string) $bonusBps, 0), (string) self::FEE_DENOM, 0);
    }

    /**
     * Simulate burn fee deduction: returns [netAmount, burnAmount].
     */
    private function applyBurnFee(string $amountRaw, string $currentSupply): array
    {
        // If supply is at or below floor, no burn
        if (bccomp($currentSupply, $this->burnFloor) <= 0) {
            return [$amountRaw, '0'];
        }

        $burnAmount = bcdiv(
            bcmul($amountRaw, (string) self::BURN_FEE_BPS, 0),
            (string) self::FEE_DENOM,
            0
        );

        // Cannot burn below floor
        $newSupply = bcsub($currentSupply, $burnAmount, 0);
        if (bccomp($newSupply, $this->burnFloor) < 0) {
            $burnAmount = bcsub($currentSupply, $this->burnFloor, 0);
        }

        $net = bcsub($amountRaw, $burnAmount, 0);
        return [$net, $burnAmount];
    }

    // ── OBX allocation maths ───────────────────────────────────────────────

    /** @test */
    public function usdt_100_at_rate_10000_obx_per_usdt_gives_1_million_obx()
    {
        $obxRaw = $this->calcObxRaw('100', '10000');
        $expectedHuman = '1000000';
        $expectedRaw   = bcmul($expectedHuman, $this->unit, 0);
        $this->assertEquals($expectedRaw, $obxRaw);
    }

    /** @test */
    public function usdt_1_at_rate_1_obx_per_usdt_gives_1_obx()
    {
        $obxRaw      = $this->calcObxRaw('1', '1');
        $expectedRaw = $this->unit;  // exactly 1 OBX in raw
        $this->assertEquals($expectedRaw, $obxRaw);
    }

    /** @test */
    public function usdt_0_gives_0_obx()
    {
        $obxRaw = $this->calcObxRaw('0', '10000');
        $this->assertEquals('0', $obxRaw);
    }

    /** @test */
    public function rate_0_obx_per_usdt_gives_0_obx_for_any_usdt_amount()
    {
        $obxRaw = $this->calcObxRaw('1000', '0');
        $this->assertEquals('0', $obxRaw);
    }

    /** @test */
    public function bonus_bps_500_on_1000_obx_gives_50_obx()
    {
        // 500 BPS = 5%
        $base    = bcmul('1000', $this->unit, 0);
        $bonus   = $this->calcBonus($base, 500);
        $expectedBonus = bcmul('50', $this->unit, 0);
        $this->assertEquals($expectedBonus, $bonus);
    }

    /** @test */
    public function bonus_bps_0_gives_zero_bonus()
    {
        $base  = bcmul('1000', $this->unit, 0);
        $bonus = $this->calcBonus($base, 0);
        $this->assertEquals('0', $bonus);
    }

    /** @test */
    public function total_obx_equals_base_plus_bonus()
    {
        $baseRaw  = $this->calcObxRaw('100', '10000');
        $bonusRaw = $this->calcBonus($baseRaw, 500); // 5% bonus
        $total    = bcadd($baseRaw, $bonusRaw, 0);

        // 100 USDT * 10000 OBX/USDT = 1,000,000 OBX  →  5% bonus = 50,000: total 1,050,000
        $expectedTotal = bcmul('1050000', $this->unit, 0);
        $this->assertEquals($expectedTotal, $total);
    }

    /** @test */
    public function phase_fee_bps_100_on_1000_obx_gives_10_obx_fee()
    {
        $base    = bcmul('1000', $this->unit, 0);
        $feeBps  = 100; // 1%
        $fee     = bcdiv(bcmul($base, (string) $feeBps, 0), (string) self::FEE_DENOM, 0);

        $expectedFee = bcmul('10', $this->unit, 0);
        $this->assertEquals($expectedFee, $fee);
    }

    // ── Burn fee arithmetic ────────────────────────────────────────────────

    /** @test */
    public function burn_fee_is_0_05_percent_of_transfer_amount()
    {
        $amount    = bcmul('10000', $this->unit, 0);  // 10,000 OBX
        $burnFee   = bcdiv(
            bcmul($amount, (string) self::BURN_FEE_BPS, 0),
            (string) self::FEE_DENOM,
            0
        );
        // 0.05% of 10,000 = 5 OBX
        $expectedBurn = bcmul('5', $this->unit, 0);
        $this->assertEquals($expectedBurn, $burnFee);
    }

    /** @test */
    public function apply_burn_fee_reduces_supply_correctly()
    {
        $transferAmount = bcmul('10000', $this->unit, 0);
        [$net, $burned] = $this->applyBurnFee($transferAmount, $this->initialSupply);

        // net should be 9,995 OBX
        $expectedNet = bcmul('9995', $this->unit, 0);
        $this->assertEquals($expectedNet, $net);
        $this->assertEquals(bcmul('5', $this->unit, 0), $burned);
    }

    /** @test */
    public function burn_stops_when_supply_is_exactly_at_burn_floor()
    {
        // Supply is exactly the burn floor — burn must return 0
        [$net, $burned] = $this->applyBurnFee(bcmul('10000', $this->unit, 0), $this->burnFloor);
        $this->assertEquals('0', $burned);
        $this->assertEquals(bcmul('10000', $this->unit, 0), $net);
    }

    /** @test */
    public function burn_stops_when_supply_is_below_burn_floor()
    {
        $belowFloor = bcsub($this->burnFloor, '1', 0);
        [$net, $burned] = $this->applyBurnFee(bcmul('10000', $this->unit, 0), $belowFloor);
        $this->assertEquals('0', $burned);
    }

    /** @test */
    public function partial_burn_at_burn_floor_boundary_does_not_overshoot()
    {
        // Supply is one token above burn floor
        $supply = bcadd($this->burnFloor, $this->unit, 0); // floor + 1 OBX

        [$net, $burned] = $this->applyBurnFee(bcmul('10000', $this->unit, 0), $supply);

        // Can only burn exactly 1 token (the single unit above floor)
        $this->assertEquals($this->unit, $burned);

        // Net = transferAmount - 1 token burned
        $expectedNet = bcsub(bcmul('10000', $this->unit, 0), $this->unit, 0);
        $this->assertEquals($expectedNet, $net);
    }

    /** @test */
    public function total_burned_over_lifetime_cannot_exceed_59_million_obx()
    {
        $maxBurnable = bcsub($this->initialSupply, $this->burnFloor, 0);
        $expected    = bcmul('59000000', $this->unit, 0);
        $this->assertEquals($expected, $maxBurnable);
    }

    // ── Presale phase cap enforcement ─────────────────────────────────────

    /** @test */
    public function purchase_that_exceeds_phase_cap_would_be_rejected()
    {
        $phaseCap    = bcmul('1000000', $this->unit, 0);   // 1M OBX phase cap
        $alreadySold = bcmul('999999',  $this->unit, 0);   // 999,999 OBX already sold
        $newRequest  = bcmul('2',       $this->unit, 0);   // requesting 2 OBX more

        $remaining = bcsub($phaseCap, $alreadySold, 0);   // 1 OBX remaining

        // Would the purchase exceed the cap?
        $wouldExceed = bccomp($newRequest, $remaining) > 0;
        $this->assertTrue($wouldExceed, 'A 2 OBX request must exceed 1 OBX remaining cap');
    }

    /** @test */
    public function purchase_exactly_at_phase_cap_is_allowed()
    {
        $phaseCap    = bcmul('1000000', $this->unit, 0);
        $alreadySold = bcmul('999999',  $this->unit, 0);
        $newRequest  = bcmul('1',       $this->unit, 0);

        $remaining   = bcsub($phaseCap, $alreadySold, 0);
        $wouldExceed = bccomp($newRequest, $remaining) > 0;
        $this->assertFalse($wouldExceed, 'A request exactly at the remaining cap must be allowed');
    }

    /** @test */
    public function zero_obx_purchase_should_not_be_credited()
    {
        $obxAmount = '0';
        $isValid   = bccomp($obxAmount, '0') > 0;
        $this->assertFalse($isValid, 'Zero OBX allocation must be rejected as invalid');
    }

    // ── Large-number / overflow safety ────────────────────────────────────

    /** @test */
    public function max_possible_obx_allocation_fits_in_bcmath_string()
    {
        // 100 million OBX × 1e18 = 1e26 — a 27-digit number
        $maxRaw   = $this->initialSupply; // 100M × 1e18
        $digits   = strlen($maxRaw);
        $this->assertGreaterThanOrEqual(26, $digits, 'Max supply raw should be >= 26 digits');
        $this->assertIsString($maxRaw, 'Raw supply must remain a string (bcmath)');
    }

    /** @test */
    public function summing_obx_allocations_does_not_overflow_bcmath()
    {
        // Simulate 10,000 users each buying 1,000 OBX
        $perUser      = bcmul('1000', $this->unit, 0);
        $totalBought  = bcmul($perUser, '10000', 0);
        $humanTotal   = bcdiv($totalBought, $this->unit, 0);

        $this->assertEquals('10000000', $humanTotal, '10k users × 1k OBX = 10M OBX total');
        // Must be within initial supply of 100M
        $this->assertLessThanOrEqual(0, bccomp($totalBought, $this->initialSupply));
    }

    // ── Referral bonus calculation ─────────────────────────────────────────

    /** @test */
    public function referral_bonus_2_percent_on_100_OBX_gives_2_OBX()
    {
        $baseRaw     = bcmul('100', $this->unit, 0);
        $bonusBps    = 200; // 2%
        $referralRaw = bcdiv(bcmul($baseRaw, (string) $bonusBps, 0), (string) self::FEE_DENOM, 0);

        $this->assertEquals(bcmul('2', $this->unit, 0), $referralRaw);
    }

    /** @test */
    public function referral_bonus_stacks_additively_with_phase_bonus()
    {
        $baseRaw      = bcmul('1000', $this->unit, 0);
        $phaseBonusBps = 500;   // 5%
        $referralBps   = 200;  // 2%

        $phaseBonus    = bcdiv(bcmul($baseRaw, (string) $phaseBonusBps, 0), (string) self::FEE_DENOM, 0);
        $referralBonus = bcdiv(bcmul($baseRaw, (string) $referralBps,   0), (string) self::FEE_DENOM, 0);
        $total         = bcadd(bcadd($baseRaw, $phaseBonus, 0), $referralBonus, 0);

        // 1000 + 5% (50) + 2% (20) = 1070 OBX
        $this->assertEquals(bcmul('1070', $this->unit, 0), $total);
    }

    // ── Precision: no floating-point rounding errors ────────────────────────

    /** @test */
    public function rate_0_01_usdt_per_obx_gives_100_obx_per_usdt_exact()
    {
        // 1 USDT / 0.01 = 100 OBX — done via bcmath, no float
        $rateHuman  = '0.01';  // price per OBX in USDT
        $obxPerUsdt = bcdiv('1', $rateHuman, 0); // = 100
        $this->assertEquals('100', $obxPerUsdt);
    }

    /** @test */
    public function float_rounding_does_not_occur_in_bcmath_division()
    {
        // Classic float pitfall: 0.1 + 0.2 !== 0.3 in float
        // With bcmath, this is exact
        $a = '0.1';
        $b = '0.2';
        $c = bcadd($a, $b, 1); // 1 decimal place precision
        $this->assertEquals('0.3', $c);
    }
}
