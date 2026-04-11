<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Model\MerchantApiKey;
use App\Model\PaymentOrder;
use App\User;
use Illuminate\Http\Request;

/**
 * MerchantController (admin)
 *
 * Lets admins view and manage all merchant API keys and payment orders.
 *
 * Routes are under /admin/merchants (prefix admin, namespace admin).
 */
class MerchantController extends Controller
{
    // ── List all merchant API keys ────────────────────────────────────────────

    public function index(Request $request)
    {
        $query = MerchantApiKey::with('user')
            ->orderByDesc('created_at');

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                  ->orWhere('api_key', 'like', "%{$s}%");
            });
        }

        $keys = $query->paginate(20)->withQueryString();

        return view('admin.merchant.index', compact('keys'));
    }

    // ── View single merchant's orders ─────────────────────────────────────────

    public function orders(Request $request, int $keyId)
    {
        $key = MerchantApiKey::with('user')->findOrFail($keyId);

        $orders = PaymentOrder::where('merchant_id', $keyId)
            ->orderByDesc('created_at')
            ->paginate(25);

        return view('admin.merchant.orders', compact('key', 'orders'));
    }

    // ── Toggle key active/inactive ────────────────────────────────────────────

    public function toggleStatus(int $id)
    {
        $key = MerchantApiKey::findOrFail($id);
        $key->update(['is_active' => !$key->is_active]);

        return redirect()->back()->with('success', 'Merchant key status updated.');
    }

    // ── Hard-delete a key (admin only) ────────────────────────────────────────

    public function destroy(int $id)
    {
        $key = MerchantApiKey::findOrFail($id);
        $key->delete();

        return redirect()->route('admin.merchant.index')->with('success', 'Merchant key deleted.');
    }
}
