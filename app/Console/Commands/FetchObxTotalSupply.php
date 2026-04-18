<?php

namespace App\Console\Commands;

use App\Model\AdminSetting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Fetches the live totalSupply() from the OBX BEP-20 token contract on BSC
 * and persists it in admin_settings (slug: obx_total_supply) so the landing
 * page and /api/obx-price always serve the real on-chain value.
 *
 * The supply decreases over time due to burn mechanics, so this command
 * should run frequently (every 5 minutes via the scheduler).
 *
 * Settings read:
 *   contract_address  — OBX token contract (BEP-20)
 *   bsc_rpc_url / chain_link — BSC JSON-RPC endpoint override
 *   obx_token_decimals       — token decimals (default 18)
 *
 * Settings written:
 *   obx_total_supply         — current total supply (human-readable, no decimals)
 */
class FetchObxTotalSupply extends Command
{
    protected $signature   = 'obx:fetch-total-supply';
    protected $description = 'Fetch live OBX totalSupply() from the BEP-20 contract and cache in settings';

    /** keccak256("totalSupply()")[0..4] */
    private const SEL_TOTAL_SUPPLY = '0x18160ddd';

    /** Fallback BSC public RPC */
    private const DEFAULT_RPC = 'https://bsc-dataseed.binance.org/';

    public function handle(): int
    {
        $contractAddress = settings('contract_address');

        if (empty($contractAddress)) {
            $this->warn('OBX contract_address is not configured in Admin Settings. Skipping.');
            return self::FAILURE;
        }

        $rpcUrl = settings('bsc_rpc_url')
            ?: settings('chain_link')
            ?: config('blockchain.rpc_url', self::DEFAULT_RPC);

        $decimals = (int) (settings('obx_token_decimals') ?: 18);

        try {
            $response = Http::timeout(15)->post($rpcUrl, [
                'jsonrpc' => '2.0',
                'method'  => 'eth_call',
                'params'  => [
                    [
                        'to'   => $contractAddress,
                        'data' => self::SEL_TOTAL_SUPPLY,
                    ],
                    'latest',
                ],
                'id' => 1,
            ]);

            $json   = $response->json();
            $result = $json['result'] ?? null;

            if (!$result || $result === '0x') {
                $this->error('Contract returned empty result. Check contract_address and RPC URL.');
                return self::FAILURE;
            }

            // Decode uint256 from 32-byte hex response
            $hex        = ltrim($result, '0x');
            $rawSupply  = hexdec(str_pad($hex, 64, '0', STR_PAD_LEFT));

            // For very large uint256 values hexdec loses precision — use bcmath
            if (strlen($hex) > 13) {
                $rawBig = '0';
                for ($i = 0; $i < strlen($hex); $i++) {
                    $rawBig = bcadd(bcmul($rawBig, '16'), (string) hexdec($hex[$i]));
                }
                $divisor    = bcpow('10', (string) $decimals);
                $totalSupply = bcdiv($rawBig, $divisor, 0); // integer tokens
            } else {
                $divisor    = 10 ** $decimals;
                $totalSupply = (string) intval($rawSupply / $divisor);
            }

            AdminSetting::updateOrCreate(
                ['slug' => 'obx_total_supply'],
                ['value' => $totalSupply]
            );

            $this->info("OBX total supply updated: {$totalSupply} OBX");
            return self::SUCCESS;

        } catch (\Throwable $e) {
            Log::error('FetchObxTotalSupply: ' . $e->getMessage());
            $this->error('Failed to fetch total supply: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
