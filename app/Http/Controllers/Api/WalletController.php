<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * WalletController
 *
 * Manages OBX blockchain wallets for authenticated users.
 *
 * Endpoints:
 *   GET    /api/user/wallets                      — list all wallets
 *   POST   /api/user/wallets/generate             — generate a new wallet
 *   PATCH  /api/user/wallets/{id}/label           — rename a wallet
 *   POST   /api/user/wallets/{id}/refresh-balance — refresh on-chain OBX balance
 *
 * All endpoints require auth:api middleware.
 * Private keys are NEVER exposed in any response.
 */
class WalletController extends Controller
{
    private WalletService $walletService;

    public function __construct(WalletService $walletService)
    {
        $this->walletService = $walletService;
    }

    /**
     * GET /api/user/wallets
     *
     * Returns all wallets for the authenticated user.
     * Primary wallet is listed first.
     */
    public function index(Request $request)
    {
        $wallets = $this->walletService->getWalletsForUser(Auth::id());

        return response()->json([
            'success' => true,
            'wallets' => $wallets->map(fn($w) => $this->walletResource($w)),
        ]);
    }

    /**
     * POST /api/user/wallets/generate
     *
     * Generate a new random OBX wallet.
     * Body (optional): { "label": "Trading Wallet" }
     * Limited to MAX_WALLETS_PER_USER per user.
     */
    public function generate(Request $request)
    {
        $request->validate([
            'label' => 'nullable|string|max:100',
        ]);

        $label = $request->input('label', 'Wallet');

        try {
            $wallet = $this->walletService->generateWallet(
                Auth::id(),
                $label,
            );

            return response()->json([
                'success' => true,
                'message' => __('Wallet generated successfully'),
                'wallet'  => $this->walletResource($wallet),
            ], 201);

        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * PATCH /api/user/wallets/{id}/label
     *
     * Rename a wallet.
     * Body: { "label": "DeFi Wallet" }
     */
    public function updateLabel(Request $request, int $id)
    {
        $request->validate([
            'label' => 'required|string|min:1|max:100',
        ]);

        try {
            $wallet = $this->walletService->updateLabel(Auth::id(), $id, $request->input('label'));

            return response()->json([
                'success' => true,
                'message' => __('Wallet label updated'),
                'wallet'  => $this->walletResource($wallet),
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => __('Wallet not found')], 404);
        }
    }

    /**
     * POST /api/user/wallets/{id}/refresh-balance
     *
     * Query the OBXToken contract on-chain and update cached_balance.
     * Returns the fresh balance string.
     */
    public function refreshBalance(int $id)
    {
        $wallet = \App\Model\ObxWallet::where('id', $id)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        try {
            $balance = $this->walletService->refreshBalance($wallet);

            return response()->json([
                'success'            => true,
                'address'            => $wallet->address,
                'balance'            => $balance,
                'balance_updated_at' => $wallet->fresh()->balance_updated_at?->toISOString(),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('Balance refresh failed: ') . $e->getMessage(),
            ], 500);
        }
    }

    // ─── Private helpers ─────────────────────────────────────────────────────

    /**
     * Serialize a wallet for API response.
     * IMPORTANT: encrypted_private_key is hidden via ObxWallet::$hidden.
     */
    private function walletResource(\App\Model\ObxWallet $wallet): array
    {
        return [
            'id'                 => $wallet->id,
            'address'            => $wallet->address,
            'label'              => $wallet->label,
            'is_primary'         => $wallet->is_primary,
            'cached_balance'     => $wallet->cached_balance,
            'balance_updated_at' => $wallet->balance_updated_at?->toISOString(),
            'created_at'         => $wallet->created_at?->toISOString(),
            // Explorer link — multi-chain
            'explorer_url'       => $this->explorerUrl($wallet->address),
        ];
    }

    private function explorerUrl(string $address): string
    {
        $chainId = (int) config('blockchain.presale_chain_id', 56);
        $base    = match ($chainId) {
            56      => 'https://bscscan.com/address/',
            97      => 'https://testnet.bscscan.com/address/',
            1       => 'https://etherscan.io/address/',
            137     => 'https://polygonscan.com/address/',
            default => 'https://bscscan.com/address/',
        };
        return $base . $address;
    }
}
