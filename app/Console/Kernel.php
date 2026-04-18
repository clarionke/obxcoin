<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        Commands\MemberBonusDistribute::class,
        Commands\CustomTokenDeposit::class,
        Commands\AdjustCustomTokenDeposit::class,
        Commands\FetchCMCPrice::class,
        Commands\ReportCMCSupply::class,
        Commands\CancelExpiredCoWalletWithdrawals::class,
        Commands\PresaleSyncEvents::class,
        Commands\RetryFailedObxDeliveries::class,
        Commands\SyncNowPaymentsStatus::class,
        Commands\FetchObxTotalSupply::class,
        Commands\ScanObxDeposits::class,
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
         $schedule->command('command:membershipbonus')
             ->daily();
        $schedule->command('custom-token-deposit')
            ->everyFiveMinutes();

        $schedule->command('adjust-token-deposit')
            ->everyThirtyMinutes();

        // Scan on-chain OBX deposits (BSCScan API — no Node.js required) every 2 minutes
        $schedule->command('obx:scan-deposits')
            ->everyTwoMinutes()
            ->withoutOverlapping()
            ->runInBackground();

        // Full on-chain balance reconciliation for all users — runs every 30 minutes
        // catches any deposits missed by the 2-minute scanner (e.g., during downtime)
        $schedule->command('balance:reconcile')
            ->everyThirtyMinutes()
            ->withoutOverlapping()
            ->runInBackground();

        // Fetch live OBX totalSupply() from the BEP-20 contract every 5 minutes
        $schedule->command('obx:fetch-total-supply')
            ->everyFiveMinutes()
            ->withoutOverlapping()
            ->runInBackground();

        // Fetch live OBX price from CoinMarketCap every 5 minutes
        $schedule->command('cmc:fetch-price')
            ->everyFiveMinutes()
            ->withoutOverlapping()
            ->runInBackground();

        // Report circulating supply to CoinMarketCap daily
        $schedule->command('cmc:report-supply')
            ->daily()
            ->withoutOverlapping();

        // Cancel expired pending co-wallet withdrawal requests automatically
        $schedule->command('co-wallet:cancel-expired-withdrawals')
            ->everyMinute()
            ->withoutOverlapping();

        // Finalize pending WalletConnect buys from on-chain TokensPurchased events
        $schedule->command('presale:sync-events')
            ->everyMinute()
            ->withoutOverlapping()
            ->runInBackground();

        // Retry failed NOWPayments OBX on-chain deliveries.
        $schedule->command('nowpayments:retry-obx-delivery --limit=100')
            ->everyMinute()
            ->withoutOverlapping()
            ->runInBackground();

        // Sync pending NOWPayments statuses in background so credit finalizes
        // even when user leaves the payment page.
        $schedule->command('nowpayments:sync-status --limit=200')
            ->everyMinute()
            ->withoutOverlapping()
            ->runInBackground();
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
