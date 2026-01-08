<?php

namespace App\Http\Controllers\User;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Services\CacheService;

class PaymentHistoryController extends Controller
{
    /**
     * Get payment history (invoices) for the authenticated user
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $fresh = (bool) $request->query('fresh', false);

        // Use CacheService with optional fresh fetch
        $invoices = CacheService::getInvoices($user->id, $fresh);

        return response()->json([
            'invoices' => $invoices,
            'last_updated' => now()->toIso8601String(),
            'cached' => !$fresh,
        ]);
    }
}
