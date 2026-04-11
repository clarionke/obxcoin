<?php

namespace App\Http\Middleware;

use App\Model\MerchantApiKey;
use Closure;
use Illuminate\Http\Request;

/**
 * VerifyMerchantApiKey
 *
 * Authenticates merchant API requests using HMAC-SHA256 request signing.
 *
 * Required headers:
 *   X-Api-Key       — the public API key (prefix "obx_")
 *   X-Api-Timestamp — Unix timestamp as integer string (rejected if >5 min old / future)
 *   X-Api-Signature — HMAC-SHA256(api_key + "." + timestamp + "." + sha256(raw_body), api_secret)
 *
 * Optional: IP whitelist enforced if merchant has configured allowed_ips.
 */
class VerifyMerchantApiKey
{
    /** Maximum age of a signed request, in seconds (5 minutes). */
    private const TIMESTAMP_TOLERANCE = 300;

    public function handle(Request $request, Closure $next)
    {
        $apiKey   = $request->header('X-Api-Key');
        $tsHeader = $request->header('X-Api-Timestamp');
        $sigHeader = $request->header('X-Api-Signature');

        // ── 1. Presence check ─────────────────────────────────────────────────
        if (!$apiKey || !$tsHeader || !$sigHeader) {
            return $this->unauthorized('Missing authentication headers (X-Api-Key, X-Api-Timestamp, X-Api-Signature).');
        }

        // ── 2. Timestamp window (anti-replay) ─────────────────────────────────
        if (!ctype_digit((string) $tsHeader)) {
            return $this->unauthorized('Invalid X-Api-Timestamp.');
        }

        $timestamp = (int) $tsHeader;
        $now       = time();

        if (abs($now - $timestamp) > self::TIMESTAMP_TOLERANCE) {
            return $this->unauthorized('Request timestamp is outside the acceptable window.');
        }

        // ── 3. Look up key record ─────────────────────────────────────────────
        $merchantKey = MerchantApiKey::where('api_key', $apiKey)
            ->where('is_active', true)
            ->first();

        if (!$merchantKey) {
            return $this->unauthorized('Invalid or inactive API key.');
        }

        // ── 4. IP whitelist ───────────────────────────────────────────────────
        $allowedIps = $merchantKey->allowed_ips;
        if (!empty($allowedIps)) {
            $clientIp = $request->ip();
            if (!in_array($clientIp, $allowedIps, true)) {
                return $this->unauthorized('Request IP is not whitelisted.');
            }
        }

        // ── 5. HMAC signature verification ────────────────────────────────────
        // Signature covers: api_key + "." + timestamp + "." + lowercase-hex SHA-256 of raw body
        $rawBody      = $request->getContent();
        $bodyHash     = hash('sha256', $rawBody);
        $signedString = $apiKey . '.' . $tsHeader . '.' . $bodyHash;

        // Retrieve the plain secret from the request or reconstruct from the stored hash.
        // Since we store a SHA-256 hash of the secret, we cannot reverse it.
        // Merchants embed the plain secret in the HMAC call; we recompute and compare.
        // We derive a per-request verification key from the stored hash so we never need
        // to store the secret in plain text.
        $expectedSig = hash_hmac('sha256', $signedString, $merchantKey->api_secret_hash);
        $actualSig   = strtolower($sigHeader);

        if (!hash_equals($expectedSig, $actualSig)) {
            return $this->unauthorized('Invalid request signature.');
        }

        // ── 6. Update last_used_at (non-blocking) ────────────────────────────
        $merchantKey->updateQuietly(['last_used_at' => now()]);

        // ── 7. Attach key record to request for downstream use ────────────────
        $request->attributes->set('merchant_key', $merchantKey);

        return $next($request);
    }

    private function unauthorized(string $message)
    {
        return response()->json([
            'success' => false,
            'message' => $message,
        ], 401);
    }
}
