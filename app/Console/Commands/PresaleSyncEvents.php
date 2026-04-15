<?php

namespace App\Console\Commands;

use App\Http\Controllers\Api\PresaleWebhookController;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PresaleSyncEvents extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'presale:sync-events';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync on-chain presale purchase events and finalize pending buys';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        try {
            $syncKey = (string) config('blockchain.sync_api_key', '');
            if (trim($syncKey) === '') {
                $this->error('PRESALE_SYNC_API_KEY is not configured.');
                Log::warning('presale:sync-events skipped because PRESALE_SYNC_API_KEY is empty');
                return self::FAILURE;
            }

            $request = Request::create('/api/presale/sync-events', 'POST');
            $request->headers->set('X-Api-Key', $syncKey);

            /** @var PresaleWebhookController $controller */
            $controller = app(PresaleWebhookController::class);
            $response = $controller->syncEvents($request);

            $status = method_exists($response, 'status') ? $response->status() : 200;
            $payload = method_exists($response, 'getData') ? (array) $response->getData(true) : [];

            if ($status >= 400) {
                $message = $payload['error'] ?? 'sync-events endpoint returned an error';
                $this->error('Presale sync failed: ' . $message);
                return self::FAILURE;
            }

            $processed = (int) ($payload['processed'] ?? 0);
            $lastBlock = (int) ($payload['last_block'] ?? 0);

            $this->info("Presale sync complete. Processed={$processed}, LastBlock={$lastBlock}");

            return self::SUCCESS;
        } catch (\Throwable $e) {
            Log::error('presale:sync-events failed: ' . $e->getMessage());
            $this->error('Presale sync exception: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
