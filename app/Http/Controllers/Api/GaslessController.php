<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\GaslessSponsorshipService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class GaslessController extends Controller
{
    private GaslessSponsorshipService $service;

    public function __construct()
    {
        $this->service = app(GaslessSponsorshipService::class);
    }

    public function quote(Request $request)
    {
        $request->validate([
            'wallet_address' => 'required|string|size:42',
            'action' => 'required|string|in:buy,stake,unstake,unlock,transfer,withdraw',
        ]);

        $userId = (int) Auth::id();
        $quote = $this->service->quote($userId, $request->wallet_address, $request->action);

        return response()->json([
            'success' => (bool) ($quote['eligible'] ?? false),
            'data' => $quote,
            'message' => $quote['message'] ?? '',
        ]);
    }

    public function sponsor(Request $request)
    {
        $request->validate([
            'wallet_address' => 'required|string|size:42',
            'action' => 'required|string|in:buy,stake,unstake,unlock,transfer,withdraw',
        ]);

        $userId = (int) Auth::id();
        $result = $this->service->sponsor($userId, $request->wallet_address, $request->action);

        return response()->json($result, $result['success'] ? 200 : 422);
    }
}
