<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\BlockchainService;

/**
 * Unit tests for BlockchainService helper methods.
 * These tests do NOT require a real RPC connection — they only test pure logic.
 */
class BlockchainServiceTest extends TestCase
{
    private BlockchainService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new BlockchainService();
    }

    /** @test */
    public function usd_rate_converts_correctly_to_contract_rate()
    {
        // Use reflection to access private method
        $method = new \ReflectionMethod(BlockchainService::class, 'usdRateToContractRate');
        $method->setAccessible(true);

        // $0.01 per OBX → 100 OBX per USDT → 100 * 1e18
        $result = $method->invoke($this->service, 0.01);
        $this->assertEquals('100000000000000000000', $result);

        // $1.00 per OBX → 1 OBX per USDT → 1 * 1e18
        $result = $method->invoke($this->service, 1.0);
        $this->assertEquals('1000000000000000000', $result);

        // $0.001 per OBX → 1000 OBX per USDT → 1000 * 1e18
        $result = $method->invoke($this->service, 0.001);
        $this->assertEquals('1000000000000000000000', $result);
    }

    /** @test */
    public function hex_to_decimal_converts_correctly()
    {
        $method = new \ReflectionMethod(BlockchainService::class, 'hexToDecimal');
        $method->setAccessible(true);

        $this->assertEquals('0', $method->invoke($this->service, '0'));
        $this->assertEquals('255', $method->invoke($this->service, 'ff'));
        $this->assertEquals('1000000', $method->invoke($this->service, 'f4240'));
    }

    /** @test */
    public function zero_usd_rate_returns_zero_contract_rate()
    {
        $method = new \ReflectionMethod(BlockchainService::class, 'usdRateToContractRate');
        $method->setAccessible(true);

        $result = $method->invoke($this->service, 0);
        $this->assertEquals('0', $result);
    }

    /** @test */
    public function decode_tokens_purchased_log_extracts_correct_fields()
    {
        $method = new \ReflectionMethod(BlockchainService::class, 'decodeTokensPurchasedLog');
        $method->setAccessible(true);

        // Simulate a BSCScan log entry
        // buyer = 0xAbCd...  (padded to 32 bytes in topic)
        // contractPhaseIndex = 0, dbPhaseId = 5
        // data: usdtAmount=100_000000 (100 USDT), obxAllocated=10000e18, bonusObx=500e18, timestamp=1712345678
        $usdtAmount   = str_pad(dechex(100000000), 64, '0', STR_PAD_LEFT);           // 100 USDT (6 dec)
        $obxAllocated = str_pad(dechex(0), 64, '0', STR_PAD_LEFT);                    // simplified for test
        $bonusObx     = str_pad(dechex(0), 64, '0', STR_PAD_LEFT);
        $timestamp    = str_pad(dechex(1712345678), 64, '0', STR_PAD_LEFT);

        $log = [
            'transactionHash' => '0xabc123',
            'blockNumber'     => '0x' . dechex(38000000),
            'topics'          => [
                '0xevent_sig',
                '0x000000000000000000000000abcdef1234567890abcdef1234567890abcdef12', // buyer
                '0x' . str_pad('0', 64, '0', STR_PAD_LEFT), // contractPhaseIndex = 0
                '0x' . str_pad(dechex(5), 64, '0', STR_PAD_LEFT), // dbPhaseId = 5
            ],
            'data' => '0x' . $usdtAmount . $obxAllocated . $bonusObx . $timestamp,
        ];

        $result = $method->invoke($this->service, $log);

        $this->assertEquals('0xabc123', $result['tx_hash']);
        $this->assertEquals(38000000, $result['block_number']);
        $this->assertEquals('0xabcdef1234567890abcdef1234567890abcdef12', $result['buyer']);
        $this->assertEquals(0, $result['contract_phase_index']);
        $this->assertEquals(5, $result['db_phase_id']);
        $this->assertEquals('100.000000', $result['usdt_amount']);
        $this->assertEquals('1712345678', $result['timestamp']);
    }
}
