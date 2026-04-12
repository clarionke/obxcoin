<?php

namespace App\Http\Controllers\user;

use App\Http\Controllers\Controller;
use App\Model\MerchantApiKey;
use App\Model\PaymentOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MerchantController extends Controller
{
    /**
     * Show the API key management dashboard.
     */
    public function keys()
    {
        $keys = MerchantApiKey::where('user_id', Auth::id())
            ->orderByDesc('created_at')
            ->get();

        $data['title']     = __('API Keys');
        $data['keys']      = $keys;
        $data['new_secret'] = session()->pull('new_api_secret');   // show-once
        $data['new_key_id'] = session()->pull('new_api_key_id');

        return view('user.merchant.keys', $data);
    }

    /**
     * Create a new API key for the authenticated user.
     */
    public function storeKey(Request $request)
    {
        $request->validate([
            'name'        => 'required|string|max:80',
            'webhook_url' => 'nullable|url|max:255',
            'allowed_ips' => 'nullable|string|max:500',
        ]);

        $activeCount = MerchantApiKey::where('user_id', Auth::id())
            ->where('is_active', true)
            ->count();

        if ($activeCount >= 10) {
            return redirect()->route('merchant.keys')
                ->with('dismiss', __('Maximum of 10 active API keys allowed.'));
        }

        $creds = MerchantApiKey::generateCredentials();

        $allowedIps = null;
        if ($request->filled('allowed_ips')) {
            $allowedIps = array_values(array_filter(array_map('trim', explode(',', $request->allowed_ips))));
        }

        $key = MerchantApiKey::create([
            'user_id'         => Auth::id(),
            'name'            => $request->name,
            'api_key'         => $creds['api_key'],
            'api_secret_hash' => $creds['api_secret_hash'],
            'allowed_ips'     => $allowedIps,
            'allowed_coins'   => null,
            'webhook_url'     => $request->webhook_url,
            'webhook_secret'  => null,
            'is_active'       => true,
        ]);

        // Flash plain secret once — never stored again
        session()->flash('new_api_secret', $creds['plain_secret']);
        session()->flash('new_api_key_id', $key->id);

        return redirect()->route('merchant.keys')
            ->with('success', __('API key created successfully. Copy your secret now — it will not be shown again.'));
    }

    /**
     * Revoke (soft-disable) an API key.
     */
    public function revokeKey(Request $request, int $id)
    {
        $key = MerchantApiKey::where('id', $id)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $key->update(['is_active' => false]);

        return redirect()->route('merchant.keys')
            ->with('success', __('API key revoked successfully.'));
    }

    /**
     * Show the API documentation page.
     */
    public function apiDocs()
    {
        $data['title'] = __('API Documentation');
        return view('user.merchant.api-docs', $data);
    }
}
