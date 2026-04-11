<?php

namespace App\Jobs;

use App\Model\PaymentOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * DispatchWebhook
 *
 * Sends an HMAC-signed JSON POST to the merchant's configured webhook URL
 * when a PaymentOrder status changes.
 *
 * Payload signature:
 *   HMAC-SHA256(raw_json_body, webhook_secret)
 *   Sent as `X-OBX-Signature` header.
 *
 * Retries: 3 attempts with exponential back-off (60s, 300s, 900s).
 */
class DispatchWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public array $backoff = [60, 300, 900];
    public int $timeout = 30;

    public function __construct(private readonly int $orderId) {}

    public function handle(): void
    {
        /** @var PaymentOrder $order */
        $order = PaymentOrder::with('merchant')->find($this->orderId);

        if (!$order) {
            return;
        }

        $merchant = $order->merchant;
        if (!$merchant) {
            return;
        }

        // Determine target URL (per-order override takes precedence)
        $url = $order->callback_url ?: $merchant->webhook_url;

        if (empty($url)) {
            return; // No webhook configured — nothing to do
        }

        // ── Build payload ─────────────────────────────────────────────────────
        $payload = json_encode([
            'event'      => 'payment.' . $order->status,
            'api_key'    => $merchant->api_key,
            'data'       => $order->toApiArray(),
            'sent_at'    => now()->toISOString(),
        ], JSON_UNESCAPED_UNICODE);

        // ── HMAC signature ────────────────────────────────────────────────────
        // Signed with the merchant's webhook_secret so they can verify authenticity.
        // If no webhook_secret is set we fall back to the stored api_secret_hash.
        $signingKey = $merchant->webhook_secret ?: $merchant->api_secret_hash;
        $signature  = hash_hmac('sha256', $payload, $signingKey);

        // ── HTTP POST ─────────────────────────────────────────────────────────
        try {
            $response = Http::withHeaders([
                'Content-Type'      => 'application/json',
                'X-OBX-Signature'   => $signature,
                'X-OBX-Event'       => 'payment.' . $order->status,
                'X-OBX-Order-Id'    => $order->uuid,
            ])
            ->timeout($this->timeout)
            ->post($url, json_decode($payload, true));

            $order->updateQuietly([
                'webhook_sent_at'   => now(),
                'webhook_response'  => mb_substr($response->body(), 0, 2000),
            ]);

            // Consider 2xx a success; anything else re-queues
            if (!$response->successful()) {
                Log::warning('DispatchWebhook: non-2xx response', [
                    'order'  => $order->uuid,
                    'status' => $response->status(),
                    'url'    => $url,
                ]);
                $this->fail(new \RuntimeException('Webhook returned HTTP ' . $response->status()));
            }
        } catch (\Throwable $e) {
            Log::error('DispatchWebhook: exception', [
                'order'   => $order->uuid,
                'message' => $e->getMessage(),
            ]);
            $this->fail($e);
        }
    }
}
