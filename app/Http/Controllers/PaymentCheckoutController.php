<?php

namespace App\Http\Controllers;

use App\Model\Coin;
use App\Model\PaymentOrder;
use Illuminate\Http\Request;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

/**
 * PaymentCheckoutController
 *
 * Serves the hosted checkout page for buyers.
 * Routes are public — anyone with the UUID link can view.
 */
class PaymentCheckoutController extends Controller
{
    /**
     * GET /pay/{uuid}
     * Renders the hosted checkout page.
     */
    public function show(string $uuid)
    {
        $order = PaymentOrder::where('uuid', $uuid)->with('coin', 'merchant')->first();

        if (!$order) {
            abort(404);
        }

        // Expire inline if needed
        if ($order->isExpired()) {
            $order->update(['status' => PaymentOrder::STATUS_EXPIRED]);
        }

        // Generate QR code — encode the pay address (or payment URI)
        $qrCode = QrCode::format('svg')
            ->size(200)
            ->margin(0)
            ->generate($order->pay_address);

        $coinIcon = $order->coin?->coin_icon
            ? asset(IMG_ICON_PATH . $order->coin->coin_icon)
            : null;

        return view('payment.checkout', compact('order', 'qrCode', 'coinIcon'));
    }
}
