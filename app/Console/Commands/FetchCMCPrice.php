<?php

namespace App\Console\Commands;

use App\Model\AdminSetting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Fetches the live OBX token price (and market data) from CoinMarketCap
 * and stores it in the admin_settings table so the rest of the app always
 * works off an up-to-date price.
 *
 * Scheduled: every 5 minutes via App\Console\Kernel.
 *
 * Settings consumed:
 *   coinmarketcap_api_key  — CMC Pro API key
 *   coinmarketcap_obx_id   — Numeric CMC listing ID for OBXCoin (prefer this over symbol lookup)
 *   coin_symbol            — fallback symbol (default "OBX")
 *
 * Settings produced/updated:
 *   coin_price               — USD price stored as used throughout the app
 *   obx_price_change_24h     — % change past 24 h
 *   obx_market_cap           — market cap USD
 *   obx_volume_24h           — 24 h trading volume USD
 *   obx_circulating_supply   — circulating supply
 *   obx_price_last_updated   — ISO-8601 timestamp from CMC
 */
class FetchCMCPrice extends Command
{
    protected $signature   = 'cmc:fetch-price';
    protected $description = 'Fetch live OBX price from CoinMarketCap and cache in settings';

    private const CMC_QUOTES_URL = 'https://pro-api.coinmarketcap.com/v1/cryptocurrency/quotes/latest';

    public function handle(): int
    {
        $apiKey = settings('coinmarketcap_api_key');

        if (empty($apiKey)) {
            $this->warn('CoinMarketCap API key is not configured (Admin → Settings → CoinMarketCap). Skipping.');
            Log::info('[FetchCMCPrice] No API key configured — skipping price fetch.');
            return self::SUCCESS;
        }

        $obxId     = settings('coinmarketcap_obx_id');
        $obxSymbol = settings('coin_symbol') ?: 'OBX';

        // Prefer numeric ID lookup (faster + unambiguous) over symbol
        $params = $obxId
            ? ['id'      => (string) $obxId,    'convert' => 'USD']
            : ['symbol'  => strtoupper($obxSymbol), 'convert' => 'USD'];

        try {
            $response = Http::withHeaders([
                'X-CMC_PRO_API_KEY' => $apiKey,
                'Accept'            => 'application/json',
            ])->timeout(15)->get(self::CMC_QUOTES_URL, $params);

            if (!$response->successful()) {
                Log::error('[FetchCMCPrice] CMC API returned HTTP ' . $response->status() . ': ' . $response->body());
                $this->error('CMC API error: HTTP ' . $response->status());
                return self::FAILURE;
            }

            $body   = $response->json();
            $status = $body['status'] ?? null;

            if (($status['error_code'] ?? 0) !== 0) {
                $msg = $status['error_message'] ?? 'Unknown error';
                Log::error("[FetchCMCPrice] CMC API error: {$msg}");
                $this->error("CMC API error: {$msg}");
                return self::FAILURE;
            }

            // Locate the token data — CMC returns keyed by ID or by SYMBOL
            $dataMap = $body['data'] ?? [];
            $token   = null;

            if ($obxId && isset($dataMap[$obxId])) {
                $token = $dataMap[$obxId];
            } elseif (!$obxId) {
                // symbol lookup: CMC returns array under the symbol key
                $bucket = $dataMap[strtoupper($obxSymbol)] ?? null;
                $token  = is_array($bucket) ? ($bucket[0] ?? null) : $bucket;
            }

            if (!$token) {
                Log::error('[FetchCMCPrice] Token not found in CMC response.', ['data_keys' => array_keys($dataMap)]);
                $this->error('Token not found in CMC response. Verify coinmarketcap_obx_id / coin_symbol setting.');
                return self::FAILURE;
            }

            $quote = $token['quote']['USD'] ?? null;
            if (!$quote) {
                Log::error('[FetchCMCPrice] No USD quote in CMC response.');
                $this->error('No USD quote in CMC response.');
                return self::FAILURE;
            }

            $price             = (string) round((float) ($quote['price']             ?? 0), 8);
            $change24h         = (string) round((float) ($quote['percent_change_24h'] ?? 0), 4);
            $marketCap         = (string) round((float) ($quote['market_cap']         ?? 0), 2);
            $volume24h         = (string) round((float) ($quote['volume_24h']         ?? 0), 2);
            $circulatingSupply = (string) round((float) ($token['circulating_supply'] ?? 0), 0);
            $lastUpdated       = $quote['last_updated'] ?? now()->toISOString();

            // Persist to settings table
            $this->upsertSetting('coin_price',             $price);
            $this->upsertSetting('obx_price_change_24h',  $change24h);
            $this->upsertSetting('obx_market_cap',        $marketCap);
            $this->upsertSetting('obx_volume_24h',        $volume24h);
            $this->upsertSetting('obx_circulating_supply',$circulatingSupply);
            $this->upsertSetting('obx_price_last_updated', $lastUpdated);

            // Clear any relevant cache
            if (function_exists('cache')) {
                cache()->forget('admin_settings');
                cache()->forget('all_settings');
            }

            $this->info("[FetchCMCPrice] OBX price updated: \${$price} ({$change24h}% 24h) — MarketCap: \${$marketCap}");
            Log::info("[FetchCMCPrice] Price updated", compact('price', 'change24h', 'marketCap', 'volume24h'));

            return self::SUCCESS;

        } catch (\Throwable $e) {
            Log::error('[FetchCMCPrice] Exception: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            $this->error('Exception: ' . $e->getMessage());
            return self::FAILURE;
        }
    }

    private function upsertSetting(string $slug, string $value): void
    {
        AdminSetting::updateOrCreate(['slug' => $slug], ['value' => $value]);
    }
}
