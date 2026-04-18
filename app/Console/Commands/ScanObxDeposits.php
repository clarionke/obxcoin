<?php

namespace App\Console\Commands;

use App\Model\AdminSetting;
use App\Model\WalletAddressHistory;
use App\Repository\CustomTokenRepository;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * ScanObxDeposits
 *
 * Scans the BscScan (Etherscan v2) API for recent OBX token Transfer events
 * and credits user wallets for any new incoming deposits.
 *
 * This command is independent of the legacy Node.js API service and provides
 * real-time deposit detection with actual BSCScan transaction hashes.
 *
 * Scheduled: every 2 minutes via App\Console\Kernel.
 *
 * Settings consumed:
 *   contract_address         — OBX BEP-20 contract
 *   bscscan_api_key          — BscScan / Etherscan v2 API key
 *   chain_id / presale_chain_id — 56 for BSC Mainnet, 97 for Testnet
 *   obx_deposit_scan_block   — last block number successfully scanned (auto-updated)
 */
class ScanObxDeposits extends Command
{
    protected $signature   = 'obx:scan-deposits {--block= : Start scanning from this block number (overrides saved cursor)}';
    protected $description = 'Scan recent OBX on-chain transfers and credit incoming deposits to user wallets';

    /** BscScan testnet API endpoint */
    private const BSCSCAN_TESTNET_API = 'https://api-testnet.bscscan.com/api';
    /** BscScan mainnet API endpoint */
    private const BSCSCAN_MAINNET_API = 'https://api.bscscan.com/api';
    /** Etherscan v2 unified endpoint */
    private const ETHERSCAN_V2_API    = 'https://api.etherscan.io/v2/api';

    /** How many tx to fetch per API call */
    private const PAGE_SIZE = 200;
    /** Minimum confirmations before crediting */
    private const MIN_CONFIRMATIONS = 1;

    public function handle(): int
    {
        $contractAddress = settings('contract_address');
        if (empty($contractAddress)) {
            $this->warn('OBX contract_address not configured in Admin Settings. Skipping.');
            return self::FAILURE;
        }

        $apiKey  = settings('bscscan_api_key') ?: env('BSCSCAN_API_KEY', '');
        $chainId = (int) (settings('chain_id') ?: settings('presale_chain_id') ?: 56);
        if ($chainId <= 0) $chainId = 56;

        // Resolve start block (CLI override → saved cursor → fallback 100 blocks ago)
        $startBlock = $this->option('block');
        if ($startBlock === null) {
            $saved = settings('obx_deposit_scan_block');
            $startBlock = $saved ? (int) $saved : null;
        } else {
            $startBlock = (int) $startBlock;
        }

        // Fetch recent transfers from BSCScan
        $transfers = $this->fetchRecentTransfers($contractAddress, $apiKey, $chainId, $startBlock);

        if (empty($transfers)) {
            $this->info('No new OBX transfers found.');
            return self::SUCCESS;
        }

        // Build lookup: lowercase address → wallet_id for all registered OBX addresses
        $knownAddresses = $this->loadKnownAddresses();

        if (empty($knownAddresses)) {
            $this->warn('No registered OBX wallet addresses found in DB.');
            return self::SUCCESS;
        }

        $repo     = new CustomTokenRepository();
        $credited = 0;
        $maxBlock = 0;

        foreach ($transfers as $tx) {
            $toAddress   = strtolower(trim((string) ($tx['to']   ?? '')));
            $txHash      = strtolower(trim((string) ($tx['hash'] ?? '')));
            $fromAddress = strtolower(trim((string) ($tx['from'] ?? '')));
            $blockNumber = (int) ($tx['blockNumber'] ?? 0);
            $confirmations = (int) ($tx['confirmations'] ?? 1);

            if ($txHash === '' || $blockNumber <= 0) continue;
            if ($confirmations < self::MIN_CONFIRMATIONS) continue;

            // Only process transfers sent TO a registered wallet address
            if (!isset($knownAddresses[$toAddress])) continue;

            // Decode amount
            $rawValue = (string) ($tx['value'] ?? '0');
            $decimals = (int) ($tx['tokenDecimal'] ?? 18);
            $amount   = $this->normalizeAmount($rawValue, $decimals);
            if ((float) $amount <= 0) continue;

            // Credit if not already processed
            $result = $repo->checkAddressAndDeposit($toAddress, $txHash, (float) $amount, $fromAddress);

            if (($result['success'] ?? false) === true) {
                $credited++;
                $this->info("Credited {$amount} OBX to wallet [{$toAddress}] tx={$txHash}");
            }

            if ($blockNumber > $maxBlock) {
                $maxBlock = $blockNumber;
            }
        }

        // Persist the highest block we've seen so next run starts from there
        if ($maxBlock > 0) {
            AdminSetting::updateOrCreate(
                ['slug' => 'obx_deposit_scan_block'],
                ['value' => (string) $maxBlock]
            );
        }

        $this->info("Deposit scan complete. transfers_checked=" . count($transfers) . " credited={$credited}");
        return self::SUCCESS;
    }

    /**
     * Load all registered OBX wallet lower-case addresses as a key → wallet_id map.
     */
    private function loadKnownAddresses(): array
    {
        return WalletAddressHistory::where('coin_type', DEFAULT_COIN_TYPE)
            ->whereNotNull('address')
            ->get(['address', 'wallet_id'])
            ->keyBy(fn($r) => strtolower(trim($r->address)))
            ->map(fn($r) => $r->wallet_id)
            ->toArray();
    }

    /**
     * Fetch recent token transfers from BscScan API.
     * Primary: Etherscan v2 API (chainid parameter). Fallback: legacy BscScan.
     */
    private function fetchRecentTransfers(string $contractAddress, string $apiKey, int $chainId, ?int $startBlock): array
    {
        $contractAddress = strtolower(trim($contractAddress));

        $params = [
            'module'          => 'account',
            'action'          => 'tokentx',
            'contractaddress' => $contractAddress,
            'page'            => 1,
            'offset'          => self::PAGE_SIZE,
            'sort'            => 'desc', // newest first so we credit quickly
        ];

        if ($startBlock !== null && $startBlock > 0) {
            $params['startblock'] = $startBlock + 1;
            $params['endblock']   = 999999999;
        }

        if ($apiKey !== '') {
            $params['apikey'] = $apiKey;
        }

        // Try Etherscan v2 first
        $rows = $this->callApi(self::ETHERSCAN_V2_API, array_merge($params, ['chainid' => $chainId]));

        // Fallback to legacy BscScan endpoint
        if (empty($rows)) {
            $endpoint = ($chainId === 97) ? self::BSCSCAN_TESTNET_API : self::BSCSCAN_MAINNET_API;
            $rows = $this->callApi($endpoint, $params);
        }

        return $rows;
    }

    private function callApi(string $endpoint, array $params): array
    {
        try {
            $response = Http::timeout(20)->get($endpoint, $params)->json();
            $status   = (string) ($response['status'] ?? '0');
            $result   = $response['result'] ?? [];

            if ($status === '1' && is_array($result)) {
                return $result;
            }
        } catch (\Throwable $e) {
            Log::warning('ScanObxDeposits::callApi failed: ' . $e->getMessage(), ['endpoint' => $endpoint]);
        }

        return [];
    }

    private function normalizeAmount(string $rawValue, int $decimals): string
    {
        $rawValue = trim($rawValue);
        if ($rawValue === '' || !preg_match('/^\d+$/', $rawValue)) return '0';
        $decimals = max(0, min(30, $decimals));
        if ($decimals === 0) return $rawValue;

        $divisor = '1' . str_repeat('0', $decimals);
        return bcdiv($rawValue, $divisor, 8);
    }
}
