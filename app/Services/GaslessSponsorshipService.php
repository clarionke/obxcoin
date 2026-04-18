<?php

namespace App\Services;

use App\Model\GaslessSponsorship;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GaslessSponsorshipService
{
    private BlockchainService $blockchain;

    private const DEFAULT_ACTIONS = ['buy', 'stake', 'unstake', 'unlock', 'transfer', 'withdraw'];

    public function __construct()
    {
        $this->blockchain = app(BlockchainService::class);
    }

    public function quote(int $userId, string $walletAddress, string $action): array
    {
        $normalizedWallet = strtolower(trim($walletAddress));
        $action = strtolower(trim($action));

        if (!$this->isEnabled()) {
            return [
                'eligible' => false,
                'message' => __('Gas sponsorship is disabled.'),
            ];
        }

        if (!preg_match('/^0x[a-f0-9]{40}$/', $normalizedWallet)) {
            return [
                'eligible' => false,
                'message' => __('Invalid wallet address.'),
            ];
        }

        if (!in_array($action, $this->allowedActions(), true)) {
            return [
                'eligible' => false,
                'message' => __('This action is not eligible for gas sponsorship.'),
            ];
        }

        if (!$this->needsTopup($normalizedWallet)) {
            return [
                'eligible' => false,
                'message' => __('Wallet already has enough native gas for this action.'),
            ];
        }

        if ($this->hasRecentSuccess($userId, $action)) {
            return [
                'eligible' => false,
                'message' => __('Please wait before requesting another sponsored transaction.'),
            ];
        }

        if ($this->userDailyCount($userId) >= $this->maxPerUserPerDay()) {
            return [
                'eligible' => false,
                'message' => __('Daily sponsorship limit reached for this account.'),
            ];
        }

        $amount = $this->amountForAction($action);

        if (bccomp($this->remainingDailyBudget(), $amount, 12) < 0) {
            return [
                'eligible' => false,
                'message' => __('Daily gas sponsorship budget exhausted.'),
            ];
        }

        return [
            'eligible' => true,
            'message' => __('Eligible for gas sponsorship.'),
            'action' => $action,
            'wallet_address' => $normalizedWallet,
            'amount_native' => $amount,
            'estimated_gas_limit' => $this->estimatedGasForAction($action),
            'chain_id' => $this->chainId(),
            'cooldown_seconds' => $this->cooldownSeconds(),
            'remaining_daily_budget' => $this->remainingDailyBudget(),
        ];
    }

    public function sponsor(int $userId, string $walletAddress, string $action): array
    {
        $quote = $this->quote($userId, $walletAddress, $action);
        if (!($quote['eligible'] ?? false)) {
            return [
                'success' => false,
                'message' => $quote['message'] ?? __('Not eligible for sponsorship.'),
                'data' => $quote,
            ];
        }

        $record = GaslessSponsorship::create([
            'user_id' => $userId,
            'wallet_address' => $quote['wallet_address'],
            'action' => $quote['action'],
            'chain_id' => $quote['chain_id'],
            'gas_amount_native' => $quote['amount_native'],
            'estimated_gas_limit' => $quote['estimated_gas_limit'],
            'status' => 'pending',
        ]);

        try {
            $result = $this->blockchain->transferNativeForGasTopup(
                $quote['wallet_address'],
                $quote['amount_native']
            );

            if (!$result || empty($result['txHash'])) {
                $err = $this->blockchain->getLastSignerError() ?: 'Gas sponsorship transfer failed';
                $record->status = 'failed';
                $record->error_message = mb_substr($err, 0, 500);
                $record->save();

                return [
                    'success' => false,
                    'message' => __('Failed to sponsor gas. Please try again.'),
                    'data' => [
                        'id' => $record->id,
                        'error' => $record->error_message,
                    ],
                ];
            }

            $record->status = 'broadcasted';
            $record->tx_hash = $result['txHash'];
            $record->save();

            return [
                'success' => true,
                'message' => __('Gas sponsorship sent successfully.'),
                'data' => [
                    'id' => $record->id,
                    'tx_hash' => $record->tx_hash,
                    'wallet_address' => $record->wallet_address,
                    'action' => $record->action,
                    'amount_native' => $record->gas_amount_native,
                    'chain_id' => $record->chain_id,
                ],
            ];
        } catch (\Throwable $e) {
            Log::error('Gasless sponsorship failed', [
                'user_id' => $userId,
                'wallet_address' => $quote['wallet_address'],
                'action' => $quote['action'],
                'error' => $e->getMessage(),
            ]);

            $record->status = 'failed';
            $record->error_message = mb_substr($e->getMessage(), 0, 500);
            $record->save();

            return [
                'success' => false,
                'message' => __('Failed to sponsor gas. Please try again.'),
                'data' => [
                    'id' => $record->id,
                    'error' => $record->error_message,
                ],
            ];
        }
    }

    private function isEnabled(): bool
    {
        if ((int) (settings('gasless_full_sponsorship') ?? 0) === 1) {
            return true;
        }

        return (int) (settings('gasless_enabled') ?? 0) === 1;
    }

    private function chainId(): int
    {
        return (int) (settings('presale_chain_id') ?: settings('chain_id') ?: config('blockchain.presale_chain_id', 56));
    }

    private function allowedActions(): array
    {
        $raw = trim((string) (settings('gasless_allowed_actions') ?? ''));
        if ($raw === '') {
            return self::DEFAULT_ACTIONS;
        }

        $actions = array_map(static fn($v) => strtolower(trim($v)), explode(',', $raw));
        $actions = array_filter($actions, static fn($v) => $v !== '');
        return array_values(array_unique($actions));
    }

    private function cooldownSeconds(): int
    {
        return max(0, (int) (settings('gasless_cooldown_seconds') ?? 120));
    }

    private function maxPerUserPerDay(): int
    {
        if ((int) (settings('gasless_full_sponsorship') ?? 0) === 1) {
            return max(1, (int) (settings('gasless_max_per_user_daily') ?? 20));
        }

        return max(1, (int) (settings('gasless_max_per_user_daily') ?? 3));
    }

    private function defaultAmountNative(): string
    {
        $value = trim((string) (settings('gasless_default_amount_native') ?? '0.0015'));
        return $this->normalizeDecimal($value, '0.0015');
    }

    private function actionAmountNativeMap(): array
    {
        $raw = trim((string) (settings('gasless_action_amount_native') ?? ''));
        if ($raw === '') {
            return [];
        }

        $map = [];
        foreach (explode(',', $raw) as $part) {
            $pieces = explode(':', $part, 2);
            if (count($pieces) !== 2) {
                continue;
            }
            $key = strtolower(trim($pieces[0]));
            $val = $this->normalizeDecimal(trim($pieces[1]), '0');
            if ($key !== '' && bccomp($val, '0', 12) > 0) {
                $map[$key] = $val;
            }
        }

        return $map;
    }

    private function amountForAction(string $action): string
    {
        $map = $this->actionAmountNativeMap();
        return $map[$action] ?? $this->defaultAmountNative();
    }

    private function estimatedGasForAction(string $action): int
    {
        return match ($action) {
            'buy' => 260000,
            'stake' => 240000,
            'unstake' => 220000,
            'unlock' => 180000,
            'transfer' => 140000,
            'withdraw' => 220000,
            default => 210000,
        };
    }

    private function dailyBudgetNative(): string
    {
        $value = trim((string) (settings('gasless_daily_budget_native') ?? '0.75'));
        return $this->normalizeDecimal($value, '0.25');
    }

    private function needsTopup(string $walletAddress): bool
    {
        $rpcUrl = trim((string) (settings('bsc_rpc_url') ?: settings('chain_link') ?: config('blockchain.bsc_rpc_url', 'https://bsc-dataseed.binance.org/')));
        if ($rpcUrl === '') {
            $rpcUrl = 'https://bsc-dataseed.binance.org/';
        }

        $minNative = trim((string) (settings('gasless_min_native_balance') ?: '0.00035'));
        if (!preg_match('/^\d+(\.\d+)?$/', $minNative)) {
            $minNative = '0.00035';
        }

        $minWei = bcmul($minNative, '1000000000000000000', 0);

        try {
            $resp = Http::timeout(12)->post($rpcUrl, [
                'jsonrpc' => '2.0',
                'method' => 'eth_getBalance',
                'params' => [$walletAddress, 'latest'],
                'id' => 1,
            ])->json();

            $hex = strtolower((string)($resp['result'] ?? '0x0'));
            if (!str_starts_with($hex, '0x')) {
                return true;
            }

            $wei = gmp_strval(gmp_init(substr($hex, 2) ?: '0', 16), 10);
            return bccomp($wei, $minWei, 0) < 0;
        } catch (\Throwable $e) {
            Log::warning('Gasless balance check failed, proceeding with sponsorship', [
                'wallet' => $walletAddress,
                'error' => $e->getMessage(),
            ]);
            return true;
        }
    }

    private function spentToday(): string
    {
        $start = Carbon::now()->startOfDay();
        $end = Carbon::now()->endOfDay();

        $sum = GaslessSponsorship::where('status', 'broadcasted')
            ->whereBetween('created_at', [$start, $end])
            ->sum('gas_amount_native');

        return $this->normalizeDecimal((string) $sum, '0');
    }

    private function remainingDailyBudget(): string
    {
        $budget = $this->dailyBudgetNative();
        $spent = $this->spentToday();
        if (bccomp($spent, $budget, 12) >= 0) {
            return '0';
        }
        return bcsub($budget, $spent, 12);
    }

    private function userDailyCount(int $userId): int
    {
        $start = Carbon::now()->startOfDay();
        $end = Carbon::now()->endOfDay();

        return GaslessSponsorship::where('user_id', $userId)
            ->where('status', 'broadcasted')
            ->whereBetween('created_at', [$start, $end])
            ->count();
    }

    private function hasRecentSuccess(int $userId, string $action): bool
    {
        $cooldown = $this->cooldownSeconds();
        if ($cooldown <= 0) {
            return false;
        }

        $threshold = Carbon::now()->subSeconds($cooldown);

        return GaslessSponsorship::where('user_id', $userId)
            ->where('action', $action)
            ->where('status', 'broadcasted')
            ->where('created_at', '>=', $threshold)
            ->exists();
    }

    private function normalizeDecimal(string $value, string $fallback): string
    {
        if (!preg_match('/^\d+(\.\d+)?$/', $value)) {
            return $fallback;
        }

        return bcadd($value, '0', 12);
    }
}
