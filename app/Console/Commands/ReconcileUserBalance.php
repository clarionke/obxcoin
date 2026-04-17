<?php

namespace App\Console\Commands;

use App\Services\BalanceSyncService;
use Illuminate\Console\Command;

class ReconcileUserBalance extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'balance:reconcile {userId? : User ID to reconcile (leave empty for all users)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reconcile user OBX wallet balance from on-chain to system database. Detects and reconciles missed deposits.';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $balanceSync = new BalanceSyncService();
        $userId = $this->argument('userId');

        if ($userId) {
            // Reconcile single user
            $this->info('Reconciling balance for user ID: ' . $userId);
            $result = $balanceSync->reconcileUserBalance((int) $userId);

            if ($result['success']) {
                $this->info('✓ ' . $result['message']);
                if (isset($result['data'])) {
                    $this->table(
                        ['Field', 'Value'],
                        collect($result['data'])->map(fn($v, $k) => [$k, is_array($v) ? json_encode($v) : $v])->toArray()
                    );
                }
            } else {
                $this->error('✗ ' . $result['message']);
            }
        } else {
            // Reconcile all users
            $this->info('Reconciling balance for all users...');
            $result = $balanceSync->reconcileAllUsersBalances();

            $this->table(
                ['Metric', 'Count'],
                [
                    ['Total Users', $result['total_users']],
                    ['Reconciled', $result['reconciled']],
                    ['Errors', $result['errors']],
                ]
            );

            if ($this->confirm('Show detailed results?', false)) {
                $this->table(
                    ['User ID', 'Status', 'Message'],
                    array_map(fn($d) => [$d['user_id'], $d['status'], $d['message']], $result['details'])
                );
            }
        }

        return Command::SUCCESS;
    }
}
