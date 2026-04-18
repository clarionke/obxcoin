<?php

namespace App\Console\Commands;

use App\Model\Wallet;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CleanupLegacyUserWallets extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'wallets:cleanup-legacy {--apply : Delete records (default is dry-run)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cleanup legacy non-OBX personal wallets that were auto-created for users';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $apply = (bool) $this->option('apply');

        $query = Wallet::query()
            ->whereRaw('LOWER(coin_type) != ?', [strtolower(DEFAULT_COIN_TYPE)])
            ->where(function ($q) {
                $q->whereNull('type')->orWhere('type', PERSONAL_WALLET);
            })
            ->where(function ($q) {
                $q->whereNull('balance')->orWhere('balance', '<=', 0);
            })
            ->where(function ($q) {
                $q->whereNull('referral_balance')->orWhere('referral_balance', '<=', 0);
            });

        $candidates = $query->get(['id', 'user_id', 'coin_type']);

        $safeIds = [];
        foreach ($candidates as $wallet) {
            if (!$this->hasHistoryReferences((int) $wallet->id)) {
                $safeIds[] = (int) $wallet->id;
            }
        }

        $this->info('Legacy wallet cleanup summary');
        $this->line('  candidates: ' . $candidates->count());
        $this->line('  safe_to_delete: ' . count($safeIds));
        $this->line('  mode: ' . ($apply ? 'apply' : 'dry-run'));

        if (!$apply) {
            $this->line('Run with --apply to delete safe records.');
            return self::SUCCESS;
        }

        if (empty($safeIds)) {
            $this->info('No wallets deleted.');
            return self::SUCCESS;
        }

        Wallet::whereIn('id', $safeIds)->delete();
        $this->info('Deleted wallets: ' . count($safeIds));

        return self::SUCCESS;
    }

    private function hasHistoryReferences(int $walletId): bool
    {
        if ($walletId <= 0) {
            return true;
        }

        if (Schema::hasTable('withdraw_histories')) {
            $exists = DB::table('withdraw_histories')
                ->where('wallet_id', $walletId)
                ->orWhere('receiver_wallet_id', $walletId)
                ->exists();
            if ($exists) {
                return true;
            }
        }

        if (Schema::hasTable('deposite_transactions')) {
            $exists = DB::table('deposite_transactions')
                ->where('sender_wallet_id', $walletId)
                ->orWhere('receiver_wallet_id', $walletId)
                ->exists();
            if ($exists) {
                return true;
            }
        }

        if (Schema::hasTable('buy_coin_referral_histories')) {
            $exists = DB::table('buy_coin_referral_histories')
                ->where('wallet_id', $walletId)
                ->exists();
            if ($exists) {
                return true;
            }
        }

        if (Schema::hasTable('referral_sign_bonus_histories')) {
            $exists = DB::table('referral_sign_bonus_histories')
                ->where('wallet_id', $walletId)
                ->exists();
            if ($exists) {
                return true;
            }
        }

        return false;
    }
}
