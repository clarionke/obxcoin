<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Model\MerchantApiKey;
use App\Model\PaymentOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

/**
 * MerchantKeyController
 *
 * Authenticated (Passport Bearer token) endpoints for merchants to manage
 * their API keys and view their payment orders.
 *
 * All routes require `auth:api` + `two_step` middleware (same as other user API routes).
 *
 * Endpoints:
 *   GET    /api/merchant/keys            — list my API keys
 *   POST   /api/merchant/keys            — create new API key (returns plain secret once)
 *   PATCH  /api/merchant/keys/{id}       — update key (name, webhook_url, allowed_ips, etc.)
 *   DELETE /api/merchant/keys/{id}       — revoke (deactivate) a key
 *   GET    /api/merchant/orders          — list my payment orders (paginated)
 *   GET    /api/merchant/orders/{uuid}   — get single order
 */
class MerchantKeyController extends Controller
{
    // ── List keys ─────────────────────────────────────────────────────────────

    public function index(): JsonResponse
    {
        $keys = MerchantApiKey::where('user_id', Auth::id())
            ->orderByDesc('created_at')
            ->get()
            ->map(fn(MerchantApiKey $k) => $this->keyToArray($k));

        return response()->json([
            'success' => true,
            'data'    => $keys,
            'message' => 'Merchant API keys.',
        ]);
    }

    // ── Create key ────────────────────────────────────────────────────────────

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name'           => ['required', 'string', 'max:100'],
            'webhook_url'    => ['nullable', 'url', 'max:500'],
            'webhook_secret' => ['nullable', 'string', 'max:128'],
            'allowed_ips'    => ['nullable', 'array'],
            'allowed_ips.*'  => ['ip'],
            'allowed_coins'  => ['nullable', 'array'],
            'allowed_coins.*' => ['string', 'max:60'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        // Limit one merchant to 10 active keys
        $count = MerchantApiKey::where('user_id', Auth::id())->where('is_active', true)->count();
        if ($count >= 10) {
            return response()->json([
                'success' => false,
                'message' => 'Maximum of 10 active API keys reached. Please revoke an existing key first.',
            ], 429);
        }

        $credentials = MerchantApiKey::generateCredentials();

        $key = MerchantApiKey::create([
            'user_id'         => Auth::id(),
            'name'            => $request->name,
            'api_key'         => $credentials['api_key'],
            'api_secret_hash' => $credentials['api_secret_hash'],
            'webhook_url'     => $request->webhook_url,
            'webhook_secret'  => $request->webhook_secret,
            'allowed_ips'     => $request->allowed_ips,
            'allowed_coins'   => $request->allowed_coins,
            'is_active'       => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'API key created. Save the secret now — it cannot be retrieved again.',
            'data'    => array_merge($this->keyToArray($key), [
                'api_secret' => $credentials['plain_secret'],  // Plain secret shown only once
            ]),
        ], 201);
    }

    // ── Update key ────────────────────────────────────────────────────────────

    public function update(Request $request, int $id): JsonResponse
    {
        $key = MerchantApiKey::where('id', $id)->where('user_id', Auth::id())->first();

        if (!$key) {
            return response()->json(['success' => false, 'message' => 'API key not found.'], 404);
        }

        $validator = Validator::make($request->all(), [
            'name'           => ['sometimes', 'string', 'max:100'],
            'webhook_url'    => ['nullable', 'url', 'max:500'],
            'webhook_secret' => ['nullable', 'string', 'max:128'],
            'allowed_ips'    => ['nullable', 'array'],
            'allowed_ips.*'  => ['ip'],
            'allowed_coins'  => ['nullable', 'array'],
            'allowed_coins.*' => ['string', 'max:60'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $key->update($request->only(['name', 'webhook_url', 'webhook_secret', 'allowed_ips', 'allowed_coins']));

        return response()->json([
            'success' => true,
            'message' => 'API key updated.',
            'data'    => $this->keyToArray($key->fresh()),
        ]);
    }

    // ── Revoke key ────────────────────────────────────────────────────────────

    public function destroy(int $id): JsonResponse
    {
        $key = MerchantApiKey::where('id', $id)->where('user_id', Auth::id())->first();

        if (!$key) {
            return response()->json(['success' => false, 'message' => 'API key not found.'], 404);
        }

        $key->update(['is_active' => false]);

        return response()->json([
            'success' => true,
            'message' => 'API key revoked.',
        ]);
    }

    // ── List orders ───────────────────────────────────────────────────────────

    public function orders(Request $request): JsonResponse
    {
        $limit = min((int) ($request->limit ?? 20), 100);

        $keyIds = MerchantApiKey::where('user_id', Auth::id())->pluck('id');

        $orders = PaymentOrder::whereIn('merchant_id', $keyIds)
            ->orderByDesc('created_at')
            ->paginate($limit);

        return response()->json([
            'success' => true,
            'data'    => [
                'orders'   => collect($orders->items())->map(fn(PaymentOrder $o) => $o->toApiArray()),
                'total'    => $orders->total(),
                'per_page' => $orders->perPage(),
                'page'     => $orders->currentPage(),
            ],
        ]);
    }

    // ── Single order ──────────────────────────────────────────────────────────

    public function showOrder(Request $request, string $uuid): JsonResponse
    {
        $keyIds = MerchantApiKey::where('user_id', Auth::id())->pluck('id');

        $order = PaymentOrder::where('uuid', $uuid)
            ->whereIn('merchant_id', $keyIds)
            ->first();

        if (!$order) {
            return response()->json(['success' => false, 'message' => 'Order not found.'], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => $order->toApiArray(),
        ]);
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function keyToArray(MerchantApiKey $key): array
    {
        return [
            'id'             => $key->id,
            'name'           => $key->name,
            'api_key'        => $key->api_key,
            'webhook_url'    => $key->webhook_url,
            'webhook_secret' => $key->webhook_secret ? '••••••••' : null,
            'allowed_ips'    => $key->allowed_ips,
            'allowed_coins'  => $key->allowed_coins,
            'is_active'      => $key->is_active,
            'last_used_at'   => $key->last_used_at?->toISOString(),
            'created_at'     => $key->created_at->toISOString(),
        ];
    }
}
