<?php

namespace App\Console\Commands;

use App\Model\TempWithdraw;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class CancelExpiredCoWalletWithdrawals extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'co-wallet:cancel-expired-withdrawals';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Auto-cancel pending co-wallet withdrawals that exceeded creator-defined approval duration';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (!Schema::hasColumn('temp_withdraws', 'expires_at')) {
            $this->line('temp_withdraws.expires_at not found; skipping.');
            return self::SUCCESS;
        }

        $count = TempWithdraw::where('status', STATUS_PENDING)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->update(['status' => STATUS_REJECTED]);

        $this->info('Expired pending withdrawals cancelled: ' . $count);

        return self::SUCCESS;
    }
}
