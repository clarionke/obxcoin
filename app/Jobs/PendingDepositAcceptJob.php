<?php

namespace App\Jobs;

use App\Model\DepositeTransaction;
use App\Repository\CustomTokenRepository;
use App\Services\Logger;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;
use Throwable;

class PendingDepositAcceptJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public $timeout = 0;
    private $transactions;
    private $adminId;
    public function __construct($transactions,$adminId)
    {
        $this->transactions =  $transactions;
        $this->adminId =  $adminId;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $tokenRepo = new CustomTokenRepository();
        try {
            storeDetailsException('PendingDepositAcceptJob', 'called');
            if (!empty($this->transactions)) {
                storeDetailsException('PendingDepositAcceptJob ', 'pending deposit list found');
                $tokenRepo->tokenReceiveManuallyByAdmin($this->transactions,$this->adminId);
            } else {
                storeDetailsException('PendingDepositAcceptJob', 'deposit list not found');
            }
        } catch (\Exception $e) {
            storeDetailsException('PendingDepositAcceptJob', $e->getMessage());
        }
    }

    /**
     * Handle a job failure.
     *
     * @param  \Throwable  $exception
     * @return void
     */
    public function failed(Throwable $exception)
    {
        Log::info(json_encode($exception));
        // Send user notification of failure, etc...
    }
}
