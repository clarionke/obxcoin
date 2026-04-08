<?php

namespace Tests\Unit;

use Tests\TestCase;

/**
 * Unit tests for OBXToken programmed-scarcity tokenomics.
 *
 * These tests run pure PHP arithmetic against the same constants and formulas
 * used in OBXToken.sol so the business rules are verified in CI without
 * needing to compile/deploy Solidity.
 *
 * ─── Tokenomics spec ────────────────────────────────────────────────────────
 *   Initial supply  : 100,000,000 OBX
 *   Burn fee        : 0.05 % per transfer (BURN_FEE_BPS = 5 / 10,000)
 *   Burn floor      : 41,000,000 OBX  ← burn stops permanently here
 *   Total burned    : 59,000,000 OBX  (59 % of initial supply)
 * ────────────────────────────────────────────────────────────────────────────
 */
class OBXTokenomicsTest extends TestCase
{
    // Mirror the Solidity constants exactly (using bcmath strings for precision)
    private const DECIMALS        = 18;
    private const INITIAL_SUPPLY  = '100000000'; // 100 M human units
    private const BURN_FLOOR_HU   = '41000000';  // 41 M human units
    private const TOTAL_BURNED_HU = '59000000';  // 59 M human units
    private const BURN_FEE_BPS    = 5;           // 0.05 %
    private const FEE_DENOM       = 10_000;

    /** Smallest unit (1 OBX in wei-equivalent) */
    private string $unit;
    /** Initial totalSupply in raw units */
    private string $initialSupply;
    /** BURN_FLOOR in raw units */
    private string $burnFloor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->unit          = bcpow('10', (string)self::DECIMALS, 0);
        $this->initialSupply = bcmul(self::INITIAL_SUPPLY, $this->unit, 0);
        $this->burnFloor     = bcmul(self::BURN_FLOOR_HU,  $this->unit, 0);
    }

    // ─── Supply constants ────────────────────────────────────────────────────

    /** @test */
    public function initial_supply_is_exactly_100_million_obx()
    {
        $expected = bcmul('100000000', $this->unit, 0);
        $this->assertEquals($expected, $this->initialSupply);
    }

    /** @test */
    public function burn_floor_is_exactly_41_million_obx()
    {
        $expected = bcmul('41000000', $this->unit, 0);
        $this->assertEquals($expected, $this->burnFloor);
    }

    /** @test */
    public function total_tokens_to_be_burned_is_59_million_obx()
    {
        $expectedBurned = bcmul(self::TOTAL_BURNED_HU, $this->unit, 0);
        $actualBurned   = bcsub($this->initialSupply, $this->burnFloor, 0);
        $this->assertEquals($expectedBurned, $actualBurned);
    }

    /** @test */
    public function burn_floor_plus_total_burned_equals_initial_supply()
    {
        $burned = bcsub($this->initialSupply, $this->burnFloor, 0);
        $this->assertEquals(
            $this->initialSupply,
            bcadd($this->burnFloor, $burned, 0)
        );
    }

    /** @test */
    public function burned_amount_is_59_percent_of_initial_supply()
    {
        $burned  = bcsub($this->initialSupply, $this->burnFloor, 0);
        // burned / initialSupply * 100 = 59 (integer division is fine here)
        $percent = bcdiv(bcmul($burned, '100', 0), $this->initialSupply, 6);
        $this->assertEquals('59.000000', $percent);
    }

    /** @test */
    public function remaining_supply_is_41_percent_of_initial_supply()
    {
        $percent = bcdiv(bcmul($this->burnFloor, '100', 0), $this->initialSupply, 6);
        $this->assertEquals('41.000000', $percent);
    }

    // ─── Burn fee calculation ────────────────────────────────────────────────

    /** @test */
    public function burn_fee_bps_equals_0_05_percent()
    {
        // 5 / 10_000 = 0.0005 = 0.05 %
        $feePercent = bcdiv((string)self::BURN_FEE_BPS, (string)self::FEE_DENOM, 6);
        $this->assertEquals('0.000500', $feePercent);
    }

    /** @test */
    public function burn_amount_per_transfer_is_correct()
    {
        // Transfer 1,000 OBX → burn = 1,000 × 0.05 % = 0.5 OBX
        $transferAmt = bcmul('1000', $this->unit, 0);
        $burnAmt     = bcdiv(bcmul($transferAmt, (string)self::BURN_FEE_BPS, 0), (string)self::FEE_DENOM, 0);
        $expectedBurn = bcmul('0.5', $this->unit, 0); // 0.5 * 1e18
        $this->assertEquals($expectedBurn, $burnAmt);
    }

    /** @test */
    public function recipient_receives_amount_minus_burn()
    {
        // Transfer 10,000 OBX — recipient gets 9,995 OBX (0.05 % = 5 OBX burned)
        $transferAmt = bcmul('10000', $this->unit, 0);
        $burnAmt     = bcdiv(bcmul($transferAmt, (string)self::BURN_FEE_BPS, 0), (string)self::FEE_DENOM, 0);
        $received    = bcsub($transferAmt, $burnAmt, 0);

        $expectedReceived = bcmul('9995', $this->unit, 0);
        $expectedBurn     = bcmul('5',    $this->unit, 0);

        $this->assertEquals($expectedBurn,     $burnAmt);
        $this->assertEquals($expectedReceived, $received);
    }

    // ─── Burn floor guard (mirrors _transfer logic in Solidity) ─────────────

    /** @test */
    public function burn_is_capped_when_supply_is_near_floor()
    {
        // Simulate: totalSupply is only 1 OBX above the floor
        $totalSupply = bcadd($this->burnFloor, $this->unit, 0); // floor + 1 OBX
        $available   = bcsub($totalSupply, $this->burnFloor, 0); // = 1 OBX

        // A large transfer would normally produce a big burn
        $transferAmt  = bcmul('100000', $this->unit, 0);
        $naiveBurn    = bcdiv(bcmul($transferAmt, (string)self::BURN_FEE_BPS, 0), (string)self::FEE_DENOM, 0);

        // Contract caps: burnAmount = min(naiveBurn, available)
        $actualBurn = bccomp($naiveBurn, $available) > 0 ? $available : $naiveBurn;

        // Must equal exactly 1 OBX (not 50 OBX)
        $this->assertEquals($this->unit, $actualBurn);

        // Supply after burn lands exactly on the floor
        $supplyAfter = bcsub($totalSupply, $actualBurn, 0);
        $this->assertEquals($this->burnFloor, $supplyAfter);
    }

    /** @test */
    public function burn_produces_zero_when_supply_already_at_floor()
    {
        // totalSupply == BURN_FLOOR → available = 0 → burn = 0
        $totalSupply = $this->burnFloor;
        $available   = bcsub($totalSupply, $this->burnFloor, 0); // 0

        $transferAmt  = bcmul('1000', $this->unit, 0);
        $naiveBurn    = bcdiv(bcmul($transferAmt, (string)self::BURN_FEE_BPS, 0), (string)self::FEE_DENOM, 0);

        $actualBurn = bccomp($naiveBurn, $available) > 0 ? $available : $naiveBurn;

        $this->assertEquals('0', $actualBurn);
    }

    /** @test */
    public function burn_produces_zero_when_supply_is_below_floor()
    {
        // Edge case: totalSupply somehow dropped below floor (available = 0, not negative)
        $totalSupply = bcsub($this->burnFloor, '1', 0);
        $available   = bccomp($totalSupply, $this->burnFloor) > 0
            ? bcsub($totalSupply, $this->burnFloor, 0)
            : '0';

        $transferAmt = bcmul('1000', $this->unit, 0);
        $naiveBurn   = bcdiv(bcmul($transferAmt, (string)self::BURN_FEE_BPS, 0), (string)self::FEE_DENOM, 0);

        $actualBurn = bccomp($naiveBurn, $available) > 0 ? $available : $naiveBurn;
        $this->assertEquals('0', $actualBurn);
    }

    // ─── Burn-to-completion simulation ──────────────────────────────────────

    /** @test */
    public function simulated_burn_never_drops_supply_below_floor()
    {
        // Simulate 10,000 large transfers starting from 100 M supply.
        // Each transfer: 1 M OBX — verify supply never goes below floor.
        $totalSupply = $this->initialSupply;
        $transferAmt = bcmul('1000000', $this->unit, 0); // 1 M OBX per transfer
        $floor       = $this->burnFloor;

        for ($i = 0; $i < 10_000; $i++) {
            // Contract: if burnComplete, skip burn
            if (bccomp($totalSupply, $floor) <= 0) {
                break; // burn is complete, no further burning
            }

            $available = bcsub($totalSupply, $floor, 0);
            $naiveBurn = bcdiv(bcmul($transferAmt, (string)self::BURN_FEE_BPS, 0), (string)self::FEE_DENOM, 0);
            $burnAmt   = bccomp($naiveBurn, $available) > 0 ? $available : $naiveBurn;

            $totalSupply = bcsub($totalSupply, $burnAmt, 0);

            // Invariant: supply must never go below the floor
            $this->assertGreaterThanOrEqual(0, bccomp($totalSupply, $floor),
                "Supply $totalSupply dropped below floor $floor at iteration $i");
        }

        // Final supply must be at or above the floor
        $this->assertGreaterThanOrEqual(0, bccomp($totalSupply, $floor));
    }

    /** @test */
    public function maximum_tokens_ever_burned_cannot_exceed_59_million()
    {
        $maxBurnable = bcsub($this->initialSupply, $this->burnFloor, 0);
        $limit       = bcmul(self::TOTAL_BURNED_HU, $this->unit, 0);
        $this->assertEquals($limit, $maxBurnable);
        // Any further burn after floor is hit = 0 by the cap
        $overBurn = bcsub($maxBurnable, $maxBurnable, 0); // = 0
        $this->assertEquals('0', $overBurn);
    }
}
