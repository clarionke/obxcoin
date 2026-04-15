<?php

namespace App\Services;

use App\Model\ObxWallet;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

/**
 * WalletService
 *
 * Generates and manages OBX blockchain wallets for users.
 *
 * Wallet generation uses the Node.js signer subprocess (contracts/signer.js)
 * which calls ethers.Wallet.createRandom() to produce a cryptographically
 * secure random Ethereum/BSC-compatible private key and address.
 *
 * Private keys are encrypted with Laravel's Crypt (AES-256-GCM, keyed by
 * APP_KEY) before being stored.  The raw key is never logged or serialized.
 *
 * Per-user limits:
 *   MAX_WALLETS_PER_USER = 10
 *   One wallet is always is_primary = true (auto-created on registration)
 */
class WalletService
{
    public const MAX_WALLETS_PER_USER = 10;

    private BlockchainService $blockchain;

    public function __construct(BlockchainService $blockchain)
    {
        $this->blockchain = $blockchain;
    }

    // ─── Public API ───────────────────────────────────────────────────────────

    /**
     * Ensure the user has a primary wallet.  Creates one if missing.
     * Called during user registration.
     */
    public function ensurePrimaryWallet(int $userId): ObxWallet
    {
        $primary = ObxWallet::where('user_id', $userId)
            ->where('is_primary', true)
            ->first();

        if ($primary) return $primary;

        return $this->generateWallet($userId, 'Primary Wallet', isPrimary: true);
    }

    /**
     * Generate a new wallet for a user.
     *
     * @throws \RuntimeException  if per-user wallet limit is reached or key generation fails.
     */
    public function generateWallet(int $userId, string $label = 'Wallet', bool $isPrimary = false): ObxWallet
    {
        $count = ObxWallet::where('user_id', $userId)->count();
        if ($count >= self::MAX_WALLETS_PER_USER) {
            throw new \RuntimeException(
                "Wallet limit reached ({$count} / " . self::MAX_WALLETS_PER_USER . ')'
            );
        }

        // Generate a random wallet via the signer subprocess
        $generated = $this->callSignerGenerateWallet();

        // Encrypt the private key before persisting — never store raw
        $encryptedKey = Crypt::encryptString($generated['privateKey']);

        // If this is the first/primary wallet, demote any existing primary
        if ($isPrimary) {
            ObxWallet::where('user_id', $userId)
                ->where('is_primary', true)
                ->update(['is_primary' => false]);
        }

        return ObxWallet::create([
            'user_id'               => $userId,
            'address'               => $generated['address'],
            'encrypted_private_key' => $encryptedKey,
            'label'                 => $label,
            'is_primary'            => $isPrimary,
        ]);
    }

    /**
     * Get all wallets for a user, newest first.
     */
    public function getWalletsForUser(int $userId): Collection
    {
        return ObxWallet::where('user_id', $userId)
            ->orderByDesc('is_primary')
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Update a wallet's label (user-facing rename).
     *
     * @throws \RuntimeException  if the wallet doesn't belong to the user.
     */
    public function updateLabel(int $userId, int $walletId, string $label): ObxWallet
    {
        $wallet = ObxWallet::where('id', $walletId)
            ->where('user_id', $userId)
            ->firstOrFail();

        $wallet->update(['label' => $label]);
        return $wallet->fresh();
    }

    /**
     * Refresh the cached on-chain OBX balance for a wallet.
     * Makes an eth_call to the OBXToken contract.
     */
    public function refreshBalance(ObxWallet $wallet): string
    {
        $balance = $this->blockchain->getObxBalance($wallet->address);

        $wallet->update([
            'cached_balance'     => $balance,
            'balance_updated_at' => now(),
        ]);

        return $balance;
    }

    // ─── Private helpers ──────────────────────────────────────────────────────

    /**
     * Call signer.js with the generateWallet action and return the result.
     * Returns ['address' => '0x...', 'privateKey' => '0x...'].
     *
     * @throws \RuntimeException on any failure.
     */
    private function callSignerGenerateWallet(): array
    {
        $signerPath = base_path('contracts/signer.js');
        if (!file_exists($signerPath)) {
            throw new \RuntimeException('signer.js not found at ' . $signerPath);
        }

        $json = json_encode(['action' => 'generateWallet'], JSON_THROW_ON_ERROR);

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $nodeBinary = config('blockchain.node_binary', 'node');
        $cmd  = escapeshellarg($nodeBinary) . ' ' . escapeshellarg($signerPath);
        $proc = proc_open($cmd, $descriptors, $pipes, base_path());

        if (!is_resource($proc)) {
            throw new \RuntimeException('Could not start signer process');
        }

        fwrite($pipes[0], $json);
        fclose($pipes[0]);

        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($proc);

        if ($stderr) {
            Log::warning('WalletService signer stderr: ' . $stderr);
        }

        $result = json_decode($stdout, true);

        if (!$result || isset($result['error'])) {
            throw new \RuntimeException('Wallet generation failed: ' . ($result['error'] ?? 'no output'));
        }

        if (!isset($result['address'], $result['privateKey'])) {
            throw new \RuntimeException('Signer returned incomplete wallet data');
        }

        // Validate it looks like a real Ethereum address
        if (!preg_match('/^0x[0-9a-f]{40}$/', $result['address'])) {
            throw new \RuntimeException('Signer returned invalid address format');
        }

        return $result;
    }
}
