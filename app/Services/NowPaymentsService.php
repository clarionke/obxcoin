<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;

/**
 * NOWPayments API wrapper.
 *
 * Docs: https://documenter.getpostman.com/view/7907941/2s93JqTRst
 *
 * Admin-configurable settings (AdminSetting slugs):
 *   nowpayments_api_key      — API key from nowpayments.io dashboard
 *   nowpayments_ipn_secret   — IPN secret for HMAC-SHA512 signature verification
 *   nowpayments_sandbox_mode — 1 = sandbox, 0 = production
 */
class NowPaymentsService
{
    private Client $client;
    private string $ipnSecret;

    public function __construct()
    {
        $sandbox  = settings('nowpayments_sandbox_mode') == '1';
        $baseUrl  = $sandbox
            ? 'https://api-sandbox.nowpayments.io/v1/'
            : 'https://api.nowpayments.io/v1/';

        $apiKey   = settings('nowpayments_api_key') ?? '';

        $this->ipnSecret = settings('nowpayments_ipn_secret') ?? '';

        $this->client = new Client([
            'base_uri' => $baseUrl,
            'timeout'  => 30,
            'headers'  => [
                'x-api-key'    => $apiKey,
                'Content-Type' => 'application/json',
                'Accept'       => 'application/json',
            ],
        ]);
    }

    /**
     * Create a new payment.
     *
     * @param  float  $priceAmount    Amount in USD the user should pay
     * @param  string $payCurrency    Crypto the user will pay in (e.g. "btc", "eth", "usdtbsc")
     * @param  int    $orderId        Our internal buy_coin_histories.id
     * @param  string $ipnCallbackUrl Full URL for IPN webhook
     * @param  string $description    Custom order description
     * @return array  NOWPayments raw response, e.g. ['payment_id', 'pay_address', 'pay_amount', ...]
     */
    public function createPayment(
        float  $priceAmount,
        string $payCurrency,
        int    $orderId,
        string $ipnCallbackUrl,
        string $description = ''
    ): array {
        try {
            $response = $this->client->post('payment', [
                'json' => [
                    'price_amount'     => $priceAmount,
                    'price_currency'   => 'usd',
                    'pay_currency'     => strtolower($payCurrency),
                    'ipn_callback_url' => $ipnCallbackUrl,
                    'order_id'         => (string) $orderId,
                    'order_description'=> $description ?: "OBX Purchase #$orderId",
                ],
            ]);

            return json_decode($response->getBody()->getContents(), true);

        } catch (RequestException $e) {
            Log::error('NowPayments::createPayment failed: ' . $e->getMessage());
            throw new \RuntimeException('NOWPayments API error: ' . $e->getMessage());
        }
    }

    /**
     * Fetch current status for a specific payment.
     */
    public function getPaymentStatus(string $paymentId): array
    {
        try {
            $response = $this->client->get("payment/$paymentId");
            return json_decode($response->getBody()->getContents(), true);
        } catch (RequestException $e) {
            Log::error('NowPayments::getPaymentStatus failed: ' . $e->getMessage());
            throw new \RuntimeException('NOWPayments API error: ' . $e->getMessage());
        }
    }

    /**
     * Get list of available payment currencies.
     */
    public function getAvailableCurrencies(): array
    {
        try {
            $response = $this->client->get('currencies');
            $data = json_decode($response->getBody()->getContents(), true);
            return $data['currencies'] ?? [];
        } catch (RequestException $e) {
            Log::error('NowPayments::getAvailableCurrencies failed: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Verify the IPN signature sent by NOWPayments.
     *
     * NOWPayments signs the alphabetically-sorted JSON with HMAC-SHA512
     * using the IPN secret. The signature is in the `x-nowpayments-sig` header.
     *
     * @param  string $rawBody      Raw request body (unsorted JSON from NOWPayments)
     * @param  string $sigHeader    Value of `x-nowpayments-sig` header
     * @return bool
     */
    public function verifyIpnSignature(string $rawBody, string $sigHeader): bool
    {
        if (empty($this->ipnSecret) || empty($sigHeader)) {
            return false;
        }

        // NOWPayments sorts the payload keys alphabetically before signing
        $decoded = json_decode($rawBody, true);
        if (!is_array($decoded)) {
            return false;
        }
        ksort($decoded);
        $sortedJson = json_encode($decoded);

        $expected = hash_hmac('sha512', $sortedJson, $this->ipnSecret);

        return hash_equals($expected, strtolower($sigHeader));
    }
}
