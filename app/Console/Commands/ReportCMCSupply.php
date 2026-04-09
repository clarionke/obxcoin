<?php

namespace App\Console\Commands;

use App\Model\AdminSetting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Reports OBX token metadata (circulating supply, total supply) to CoinMarketCap
 * via their self-reporting / partner API so the CMC listing stays accurate.
 *
 * CMC endpoint used:
 *   POST https://pro-api.coinmarketcap.com/v1/cryptocurrency/info/update
 *   (Enterprise/Partner API — requires a CMC Pro API key with reporting permissions)
 *
 * Scheduled: daily via App\Console\Kernel.
 *
 * Settings consumed:
 *   coinmarketcap_api_key   — CMC Pro API key (must have "write" permission)
 *   coinmarketcap_obx_id    — Numeric CMC listing ID for OBXCoin
 *   obx_circulating_supply  — updated by FetchCMCPrice / can be manually set
 *   obx_total_supply        — total OBX supply (set once after token deploy)
 *
 * Notes:
 *  - CMC price data is pulled FROM exchanges/DEXes automatically; you cannot
 *    push price to CMC. This command only updates supply metadata.
 *  - For DEX-listed tokens (PancakeSwap/BSC) price auto-flows once the token
 *    is approved for listing by CoinMarketCap.
 *  - Self-report endpoint docs: https://coinmarketcap.com/api/documentation/v1/
 */
class ReportCMCSupply extends Command
{
    protected $signature   = 'cmc:report-supply';
    protected $description = 'Report OBX circulating / total supply to CoinMarketCap self-reporting API';

    private const CMC_INFO_UPDATE_URL = 'https://pro-api.coinmarketcap.com/v1/cryptocurrency/listings/info/update';

    public function handle(): int
    {
        $apiKey = settings('coinmarketcap_api_key');
        $obxId  = settings('coinmarketcap_obx_id');

        if (empty($apiKey) || empty($obxId)) {
            $this->warn('CMC API key or OBX listing ID not configured. Skipping supply report.');
            Log::info('[ReportCMCSupply] Skipped — missing coinmarketcap_api_key or coinmarketcap_obx_id.');
            return self::SUCCESS;
        }

        $circulatingSupply = (float) (settings('obx_circulating_supply') ?: 0);
        $totalSupply       = (float) (settings('obx_total_supply') ?: 100_000_000);

        if ($circulatingSupply <= 0) {
            $this->warn('obx_circulating_supply is not set or zero. Run cmc:fetch-price first.');
            return self::SUCCESS;
        }

        try {
            $response = Http::withHeaders([
                'X-CMC_PRO_API_KEY' => $apiKey,
                'Accept'            => 'application/json',
            ])->timeout(15)->post(self::CMC_INFO_UPDATE_URL, [
                'id'                 => (int) $obxId,
                'circulating_supply' => $circulatingSupply,
                'total_supply'       => $totalSupply,
                'self_reported'      => true,
            ]);

            $body   = $response->json();
            $status = $body['status'] ?? null;

            if (!$response->successful() || ($status['error_code'] ?? 0) !== 0) {
                $msg = $status['error_message'] ?? ('HTTP ' . $response->status());
                Log::error("[ReportCMCSupply] CMC API error: {$msg}", ['body' => $body]);
                $this->error("CMC supply report failed: {$msg}");
                // Not treated as hard failure — listing data may still be accurate
                return self::SUCCESS;
            }

            $this->info("[ReportCMCSupply] Circulating supply reported: {$circulatingSupply} OBX, total: {$totalSupply} OBX");
            Log::info('[ReportCMCSupply] Supply reported successfully.', [
                'circulating' => $circulatingSupply,
                'total'       => $totalSupply,
            ]);

            return self::SUCCESS;

        } catch (\Throwable $e) {
            Log::error('[ReportCMCSupply] Exception: ' . $e->getMessage());
            $this->error('Exception: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
