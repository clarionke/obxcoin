<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * CmcTickerController
 *
 * Exposes two public endpoints in the CoinMarketCap exchange integration format
 * so CMC can poll this application directly during the listing/onboarding process.
 *
 * Endpoints:
 *   GET /api/cmc/summary   — high-level market summary for the OBX/USDT pair
 *   GET /api/cmc/ticker    — ticker data (last price, bid, ask, volume)
 *   GET /api/obx-price     — simple JSON price for internal AJAX use
 *
 * CMC integration docs:
 *   https://coinmarketcap.com/api/documentation/v1/#section/Integration-Types
 */
class CmcTickerController extends Controller
{
    /**
     * GET /api/cmc/summary
     * Returns market summary in CMC exchange API format.
     */
    public function summary(): JsonResponse
    {
        $price     = (float) (settings('coin_price') ?: 0);
        $volume    = (float) (settings('obx_volume_24h') ?: 0);
        $marketCap = (float) (settings('obx_market_cap') ?: 0);
        $change24h = (float) (settings('obx_price_change_24h') ?: 0);
        $supply    = (float) (settings('obx_circulating_supply') ?: 0);
        $lastUpdated = settings('obx_price_last_updated') ?: now()->toISOString();

        return response()->json([
            'OBX_USDT' => [
                'trading_pairs'       => 'OBX_USDT',
                'base_currency'       => 'OBX',
                'quote_currency'      => 'USDT',
                'last_price'          => $price,
                'lowest_ask'          => $price,
                'highest_bid'         => $price,
                'base_volume'         => $volume > 0 ? round($volume / max($price, 0.00000001), 2) : 0,
                'quote_volume'        => $volume,
                'price_change_percent_24h' => $change24h,
                'highest_price_24h'   => $price, // use live price; DEX provides OHLC separately
                'lowest_price_24h'    => $price,
            ],
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate')
          ->header('Access-Control-Allow-Origin', '*');
    }

    /**
     * GET /api/cmc/ticker
     * Returns ticker data in CMC exchange API format.
     */
    public function ticker(): JsonResponse
    {
        $price     = (float) (settings('coin_price') ?: 0);
        $volume    = (float) (settings('obx_volume_24h') ?: 0);
        $supply    = (float) (settings('obx_circulating_supply') ?: 0);
        $change24h = (float) (settings('obx_price_change_24h') ?: 0);
        $lastUpdated = settings('obx_price_last_updated') ?: now()->toISOString();

        return response()->json([
            'OBX_USDT' => [
                'base_id'         => settings('coinmarketcap_obx_id') ?: '',
                'base_name'       => settings('coin_name') ?: 'OBXCoin',
                'base_symbol'     => settings('coin_symbol') ?: 'OBX',
                'quote_id'        => '825',          // USDT CMC ID
                'quote_name'      => 'Tether USD',
                'quote_symbol'    => 'USDT',
                'last_price'      => $price,
                'base_volume'     => $volume > 0 ? round($volume / max($price, 0.00000001), 2) : 0,
                'quote_volume'    => $volume,
                'isFrozen'        => '0',
                'percent_change_24h' => $change24h,
            ],
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate')
          ->header('Access-Control-Allow-Origin', '*');
    }

    /**
     * GET /api/obx-price
     * Internal lightweight price endpoint — used by AJAX on the staking/dashboard pages.
     * Returns price + 24h change so the UI can auto-refresh without a full page reload.
     */
    public function obxPrice(): JsonResponse
    {
        return response()->json([
            'price'                => (float) (settings('coin_price') ?: 0),
            'change_24h'           => (float) (settings('obx_price_change_24h') ?: 0),
            'market_cap'           => (float) (settings('obx_market_cap') ?: 0),
            'volume_24h'           => (float) (settings('obx_volume_24h') ?: 0),
            'circulating_supply'   => (float) (settings('obx_circulating_supply') ?: 0),
            'last_updated'         => settings('obx_price_last_updated') ?: null,
            'symbol'               => settings('coin_symbol') ?: 'OBX',
        ])->header('Cache-Control', 'max-age=30, public')
          ->header('Access-Control-Allow-Origin', '*');
    }
}
