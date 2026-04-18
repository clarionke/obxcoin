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
            $this->warn('CoinMarketCap API key is not configured (Admin → Settings → CoinMarketCap). Trying DEX fallback.');
            return $this->fetchFromDex();
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
            $this->warn('Falling back to DEX pricing.');
            return $this->fetchFromDex();
        }
    }

    /**
     * Fallback price source using DexScreener pair data.
     *
     * Required configuration (admin setting or .env fallback):
     * - obx_dex_pair_address / OBX_DEX_PAIR
     * - obx_dex_chain / OBX_DEX_CHAIN (default: bsc)
     */
    private function fetchFromDex(): int
    {
        $pairAddress = trim((string) (settings('obx_dex_pair_address') ?: config('blockchain.obx_dex_pair', '')));
        $chain       = strtolower(trim((string) (settings('obx_dex_chain') ?: config('blockchain.obx_dex_chain', 'bsc'))));

        if ($pairAddress === '') {
            Log::warning('[FetchCMCPrice] DEX fallback skipped — missing pair address.');
            $this->warn('DEX fallback skipped: set obx_dex_pair_address in settings (or OBX_DEX_PAIR in .env).');
            return self::SUCCESS;
        }

        $url = sprintf('https://api.dexscreener.com/latest/dex/pairs/%s/%s', $chain, $pairAddress);

        try {
            $response = Http::timeout(15)->get($url);
            if (!$response->successful()) {
                Log::error('[FetchCMCPrice] DexScreener API returned HTTP ' . $response->status() . ': ' . $response->body());
                $this->error('DexScreener API error: HTTP ' . $response->status());
                return self::FAILURE;
            }

            $body  = $response->json();
            $pairs = $body['pairs'] ?? [];
            $pair  = is_array($pairs) ? ($pairs[0] ?? null) : null;

            if (!$pair) {
                Log::warning('[FetchCMCPrice] DexScreener returned no pair data.', ['chain' => $chain, 'pair' => $pairAddress]);
                $this->warn('DexScreener returned no pair data yet.');
                return self::SUCCESS;
            }

            $price             = (string) round((float) ($pair['priceUsd']                ?? 0), 8);
            $change24h         = (string) round((float) ($pair['priceChange']['h24']       ?? 0), 4);
            $marketCapRaw      = (float) ($pair['marketCap'] ?? 0);
            $fdvRaw            = (float) ($pair['fdv']       ?? 0);
            $volume24h         = (string) round((float) ($pair['volume']['h24']            ?? 0), 2);
            $lastUpdated       = now()->toISOString();
            $circulatingSupply = (float) (settings('obx_circulating_supply') ?: settings('obx_total_supply') ?: 0);

            if ($circulatingSupply <= 0 && (float) $price > 0) {
                // If supply isn't configured, infer a best-effort supply from fdv/price.
                $circulatingSupply = $fdvRaw > 0 ? ($fdvRaw / (float) $price) : 0;
            }

            $marketCap = $marketCapRaw > 0
                ? $marketCapRaw
                : ($fdvRaw > 0
                    ? $fdvRaw
                    : ((float) $price * max($circulatingSupply, 0)));

            $this->upsertSetting('coin_price',              $price);
            $this->upsertSetting('obx_price_change_24h',    $change24h);
            $this->upsertSetting('obx_market_cap',          (string) round($marketCap, 2));
            $this->upsertSetting('obx_volume_24h',          $volume24h);
            $this->upsertSetting('obx_circulating_supply',  (string) round($circulatingSupply, 0));
            $this->upsertSetting('obx_price_last_updated',  $lastUpdated);

            if (function_exists('cache')) {
                cache()->forget('admin_settings');
                cache()->forget('all_settings');
            }

            $this->info("[FetchCMCPrice] DEX fallback updated OBX price: \${$price} ({$change24h}% 24h) — MarketCap: $" . round($marketCap, 2));
            Log::info('[FetchCMCPrice] DEX fallback price updated.', [
                'price'        => $price,
                'change24h'    => $change24h,
                'marketCap'    => round($marketCap, 2),
                'volume24h'    => $volume24h,
                'chain'        => $chain,
                'pair_address' => $pairAddress,
            ]);

            return self::SUCCESS;

        } catch (\Throwable $e) {
            Log::error('[FetchCMCPrice] DEX fallback exception: ' . $e->getMessage());
            $this->error('DEX fallback exception: ' . $e->getMessage());
            return self::FAILURE;
        }
    }

    private function upsertSetting(string $slug, string $value): void
    {
        AdminSetting::updateOrCreate(['slug' => $slug], ['value' => $value]);
    }
}
