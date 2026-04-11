<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\DispatchWebhook;
use App\Model\Coin;
use App\Model\DepositeTransaction;
use App\Model\MerchantApiKey;
use App\Model\PaymentOrder;
use App\Model\Wallet;
use App\Model\WalletAddressHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * PaymentGatewayController
 *
 * Public-facing merchant payment gateway API.
 * All mutating endpoints require HMAC authentication via VerifyMerchantApiKey middleware.
 *
 * Endpoints:
 *   GET  /api/payment/coins                    — list coins available for payment
 *   POST /api/payment/orders                   — create a new payment invoice
 *   GET  /api/payment/orders/{uuid}            — retrieve order details + current status
 *   GET  /api/payment/orders/{uuid}/status     — lightweight polling: status + amounts only
 *   POST /api/payment/orders/{uuid}/check      — trigger on-chain deposit scan
 */
class PaymentGatewayController extends Controller
{
    /** Default invoice lifetime in minutes. */
    private const DEFAULT_EXPIRY_MINUTES = 30;

    // ── Public: list supported coins ─────────────────────────────────────────

    /**
     * GET /api/payment/coins
     * No authentication required.
     */
    public function listCoins(): JsonResponse
    {
        $coins = Coin::where('status', STATUS_ACTIVE)
            ->where('is_deposit', 1)
            ->select('id', 'name', 'type', 'sign', 'coin_icon', 'minimum_buy_amount')
            ->get()
            ->map(fn(Coin $c) => [
                'id'          => $c->id,
                'name'        => $c->name,
                'type'        => $c->type,
                'symbol'      => $c->sign,
                'icon'        => $c->coin_icon ? asset(IMG_ICON_PATH . $c->coin_icon) : null,
                'min_amount'  => $c->minimum_buy_amount,
            ]);

        return response()->json([
            'success' => true,
            'data'    => $coins,
            'message' => 'Supported payment coins.',
        ]);
    }

    // ── Authenticated: create order ───────────────────────────────────────────

    /**
     * POST /api/payment/orders
     * Headers: X-Api-Key, X-Api-Timestamp, X-Api-Signature
     *
     * Body params:
     *   coin_type        (required) — must match Coin.type, e.g. "OBXCoin"
     *   amount           (required) — decimal, > 0
     *   merchant_order_id (optional) — your own reference, must be unique per key
     *   expiry_minutes   (optional) — 5–1440, default 30
     *   callback_url     (optional) — overrides key-level webhook URL for this order
     *   metadata         (optional) — arbitrary JSON object
     */
    public function createOrder(Request $request): JsonResponse
    {
        /** @var MerchantApiKey $merchantKey */
        $merchantKey = $request->attributes->get('merchant_key');

        $validator = Validator::make($request->all(), [
            'coin_type'         => ['required', 'string', 'max:60'],
            'amount'            => ['required', 'numeric', 'gt:0'],
            'merchant_order_id' => ['nullable', 'string', 'max:200'],
            'expiry_minutes'    => ['nullable', 'integer', 'min:5', 'max:1440'],
            'callback_url'      => ['nullable', 'url', 'max:500'],
            'metadata'          => ['nullable', 'array'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        // ── Find the requested coin ───────────────────────────────────────────
        $coin = Coin::where('type', $request->coin_type)
            ->where('status', STATUS_ACTIVE)
            ->where('is_deposit', 1)
            ->first();

        if (!$coin) {
            return response()->json([
                'success' => false,
                'message' => 'Coin type not found or not available for deposit.',
            ], 422);
        }

        // ── Check coin is permitted for this key ──────────────────────────────
        $allowedCoins = $merchantKey->allowed_coins;
        if (!empty($allowedCoins) && !in_array($request->coin_type, $allowedCoins, true)) {
            return response()->json([
                'success' => false,
                'message' => 'This coin type is not permitted for your API key.',
            ], 403);
        }

        // ── Uniqueness of merchant_order_id per key ───────────────────────────
        if ($request->merchant_order_id) {
            $duplicate = PaymentOrder::where('merchant_id', $merchantKey->id)
                ->where('merchant_order_id', $request->merchant_order_id)
                ->exists();

            if ($duplicate) {
                return response()->json([
                    'success' => false,
                    'message' => 'merchant_order_id already exists for this API key.',
                ], 409);
            }
        }

        // ── Resolve deposit address ───────────────────────────────────────────
        $payAddress = $this->resolveDepositAddress($coin);

        if (!$payAddress) {
            return response()->json([
                'success' => false,
                'message' => 'Unable to allocate a payment address for this coin. Please contact support.',
            ], 503);
        }

        // ── Create the order ──────────────────────────────────────────────────
        $expiry_minutes = (int) ($request->expiry_minutes ?? self::DEFAULT_EXPIRY_MINUTES);

        try {
            $order = PaymentOrder::create([
                'merchant_id'       => $merchantKey->id,
                'merchant_order_id' => $request->merchant_order_id,
                'coin_type'         => $coin->type,
                'coin_id'           => $coin->id,
                'amount'            => $request->amount,
                'pay_address'       => $payAddress,
                'status'            => PaymentOrder::STATUS_PENDING,
                'callback_url'      => $request->callback_url,
                'metadata'          => $request->metadata,
                'expires_at'        => now()->addMinutes($expiry_minutes),
            ]);
        } catch (\Throwable $e) {
            Log::error('PaymentGateway: createOrder failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to create payment order.',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Payment order created.',
            'data'    => $order->toApiArray(),
        ], 201);
    }

    // ── Authenticated: get order ──────────────────────────────────────────────

    /**
     * GET /api/payment/orders/{uuid}
     */
    public function getOrder(Request $request, string $uuid): JsonResponse
    {
        /** @var MerchantApiKey $merchantKey */
        $merchantKey = $request->attributes->get('merchant_key');

        $order = PaymentOrder::where('uuid', $uuid)
            ->where('merchant_id', $merchantKey->id)
            ->first();

        if (!$order) {
            return response()->json(['success' => false, 'message' => 'Order not found.'], 404);
        }

        // Refresh expiry status inline
        $this->maybeExpireOrder($order);

        return response()->json([
            'success' => true,
            'data'    => $order->toApiArray(),
        ]);
    }

    // ── Public: status polling (used by checkout page) ────────────────────────

    /**
     * GET /api/payment/orders/{uuid}/status
     * No auth required — used by the hosted checkout page JS poller.
     * Returns minimal data: status, amounts, expires_at only.
     */
    public function pollStatus(string $uuid): JsonResponse
    {
        $order = PaymentOrder::where('uuid', $uuid)->first();

        if (!$order) {
            return response()->json(['success' => false, 'message' => 'Order not found.'], 404);
        }

        $this->maybeExpireOrder($order);

        return response()->json([
            'success' => true,
            'data'    => [
                'status'          => $order->status,
                'amount'          => (string) $order->amount,
                'amount_received' => (string) $order->amount_received,
                'expires_at'      => $order->expires_at?->toISOString(),
                'confirmed_at'    => $order->confirmed_at?->toISOString(),
                'coin_type'       => $order->coin_type,
            ],
        ]);
    }

    // ── Public: trigger deposit scan ──────────────────────────────────────────

    /**
     * POST /api/payment/orders/{uuid}/check
     * Public endpoint — called by checkout page or merchant server.
     * Scans DepositeTransaction records for the order address and
     * promotes the order to completed / confirming / underpaid as appropriate.
     */
    public function checkDeposit(string $uuid): JsonResponse
    {
        $order = PaymentOrder::where('uuid', $uuid)->first();

        if (!$order) {
            return response()->json(['success' => false, 'message' => 'Order not found.'], 404);
        }

        if (in_array($order->status, [PaymentOrder::STATUS_COMPLETED, PaymentOrder::STATUS_EXPIRED])) {
            return response()->json([
                'success' => true,
                'data'    => ['status' => $order->status],
                'message' => 'Order is already finalised.',
            ]);
        }

        $this->maybeExpireOrder($order);
        if ($order->status === PaymentOrder::STATUS_EXPIRED) {
            return response()->json([
                'success' => true,
                'data'    => ['status' => PaymentOrder::STATUS_EXPIRED],
            ]);
        }

        // ── Scan DepositeTransaction table for matching address ───────────────
        $received = DepositeTransaction::where('address', $order->pay_address)
            ->whereIn('status', [STATUS_SUCCESS, STATUS_ACCEPTED])
            ->sum('amount');

        $received = (float) $received;
        $expected = (float) $order->amount;

        if ($received <= 0) {
            return response()->json([
                'success' => true,
                'data'    => ['status' => $order->status, 'amount_received' => '0'],
                'message' => 'No deposit detected yet.',
            ]);
        }

        // Determine new status
        if ($received >= $expected) {
            $newStatus = PaymentOrder::STATUS_COMPLETED;
        } elseif ($received > 0) {
            // Partial payment — mark underpaid only after expiry, confirming while still open
            $newStatus = $order->isExpired()
                ? PaymentOrder::STATUS_UNDERPAID
                : PaymentOrder::STATUS_CONFIRMING;
        }

        // ── Update order ──────────────────────────────────────────────────────
        $updates = [
            'amount_received' => $received,
            'status'          => $newStatus,
        ];

        if ($newStatus === PaymentOrder::STATUS_COMPLETED && !$order->confirmed_at) {
            $updates['confirmed_at'] = now();
        }

        $order->update($updates);
        $order->refresh();

        // ── Fire webhook on completion ────────────────────────────────────────
        if ($newStatus === PaymentOrder::STATUS_COMPLETED && !$order->webhook_sent_at) {
            DispatchWebhook::dispatch($order->id);
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'status'          => $order->status,
                'amount_received' => (string) $order->amount_received,
            ],
        ]);
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * Resolve a deposit address for the given coin.
     *
     * Strategy:
     *  1. Find the system wallet for this coin type (the admin/platform wallet).
     *  2. Return its most recent active address from wallet_address_histories.
     *  3. Fallback: return the wallet's key field if no address history exists.
     */
    private function resolveDepositAddress(Coin $coin): ?string
    {
        // Try WalletAddressHistory for a platform-owned address for this coin type
        $addressRecord = WalletAddressHistory::where('coin_type', $coin->type)
            ->whereNotNull('address')
            ->where('address', '!=', '')
            ->latest()
            ->first();

        if ($addressRecord) {
            return $addressRecord->address;
        }

        // Fallback: look for any wallet with this coin type
        $wallet = Wallet::where('coin_type', $coin->type)
            ->whereNotNull('key')
            ->first();

        return $wallet?->key;
    }

    /**
     * If an order has passed its expiry and is still in a non-final state, mark it expired.
     */
    private function maybeExpireOrder(PaymentOrder $order): void
    {
        if ($order->isExpired()) {
            $order->update(['status' => PaymentOrder::STATUS_EXPIRED]);
        }
    }
}
