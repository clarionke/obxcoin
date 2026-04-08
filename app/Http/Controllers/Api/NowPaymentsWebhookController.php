<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Model\BuyCoinHistory;
use App\Model\Wallet;
use App\Services\NowPaymentsService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Handles IPN (Instant Payment Notification) callbacks from NOWPayments.
 *
 * Route: POST /api/nowpayments/ipn  (unauthenticated, verified by HMAC-SHA512)
 *
 * NOWPayments lifecycle statuses:
 *   waiting → confirming → confirmed → sending → partially_paid → finished
 *   failed | refunded | expired
 *
 * We credit OBX tokens when payment_status == 'finished'.
 */
class NowPaymentsWebhookController extends Controller
{
    public function handleIpn(Request $request): Response
    {
        // ── 1. Read raw body ───────────────────────────────────────────────
        $rawBody  = $request->getContent();
        $sigHeader = $request->header('x-nowpayments-sig', '');

        // ── 2. Verify HMAC-SHA512 signature ───────────────────────────────
        $nowPayments = new NowPaymentsService();
        if (!$nowPayments->verifyIpnSignature($rawBody, $sigHeader)) {
            Log::warning('NowPaymentsIPN: invalid signature', [
                'ip'  => $request->ip(),
                'sig' => $sigHeader,
            ]);
            return response('Unauthorized', 401);
        }

        // ── 3. Parse payload ──────────────────────────────────────────────
        $data = json_decode($rawBody, true);
        if (!is_array($data)) {
            Log::warning('NowPaymentsIPN: malformed JSON body');
            return response('Bad Request', 400);
        }

        $paymentId     = $data['payment_id']     ?? null;
        $paymentStatus = $data['payment_status'] ?? null;
        $orderId       = $data['order_id']       ?? null;

        Log::info("NowPaymentsIPN: payment_id=$paymentId status=$paymentStatus order=$orderId");

        if (!$paymentId || !$paymentStatus) {
            return response('OK', 200); // Not a valid IPN — ignore silently
        }

        // ── 4. Find the purchase record ───────────────────────────────────
        $purchase = BuyCoinHistory::where('nowpayments_payment_id', (string) $paymentId)->first();

        if (!$purchase) {
            // Try by order_id as fallback (orderId = buy_coin_histories.id)
            $purchase = BuyCoinHistory::find((int) $orderId);
        }

        if (!$purchase) {
            Log::warning("NowPaymentsIPN: no BuyCoinHistory for payment_id=$paymentId order=$orderId");
            return response('OK', 200);
        }

        // ── 5. Handle status transitions ──────────────────────────────────
        if (in_array($paymentStatus, ['failed', 'expired', 'refunded'])) {
            $purchase->update(['status' => STATUS_REJECTED]);
            Log::info("NowPaymentsIPN: order #{$purchase->id} marked REJECTED ($paymentStatus)");
            return response('OK', 200);
        }

        if ($paymentStatus !== 'finished') {
            // Intermediate status (waiting, confirming, confirmed, sending, partially_paid)
            // Update the stored payment_id in case we created the record before receiving it
            if ($paymentId && !$purchase->nowpayments_payment_id) {
                $purchase->update(['nowpayments_payment_id' => (string) $paymentId]);
            }
            Log::info("NowPaymentsIPN: order #{$purchase->id} intermediate status=$paymentStatus — no action");
            return response('OK', 200);
        }

        // ── 6. Finished — credit OBX ──────────────────────────────────────
        if ($purchase->status == STATUS_SUCCESS) {
            // Idempotency: already credited
            return response('OK', 200);
        }

        DB::beginTransaction();
        try {
            $purchase->update(['status' => STATUS_SUCCESS]);

            $wallet = Wallet::where([
                'user_id'    => $purchase->user_id,
                'coin_type'  => DEFAULT_COIN_TYPE,
                'is_primary' => 1,
            ])->first();

            if ($wallet) {
                $wallet->increment('balance', (float) $purchase->requested_amount);
                Log::info("NowPaymentsIPN: credited {$purchase->requested_amount} OBX to user #{$purchase->user_id}");
            } else {
                Log::warning("NowPaymentsIPN: no primary OBX wallet for user #{$purchase->user_id}");
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('NowPaymentsIPN: DB error for payment_id=' . $paymentId . ': ' . $e->getMessage());
            // Return 200 so NOWPayments does not retry; alert is in logs
        }

        return response('OK', 200);
    }
}
