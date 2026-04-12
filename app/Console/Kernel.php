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
