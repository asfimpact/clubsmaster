<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class PaymentHistoryController extends Controller
{
    /**
     * Get user's payment history from Stripe invoices.
     * Cached for 1 hour to avoid slow Stripe API calls.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        // If user has no Stripe ID, return empty array (free trial users)
        if (!$user->stripe_id) {
            return response()->json(['invoices' => []]);
        }

        // Cache for 1 hour - invoices don't change often
        $invoices = cache()->remember("user_invoices_{$user->id}", 3600, function () use ($user) {
            try {
                $stripeInvoices = $user->invoices();

                return $stripeInvoices->map(function ($invoice) {
                    return [
                        'id' => $invoice->id,
                        'date' => $invoice->date()->format('M d, Y'),
                        'amount' => $invoice->total(), // Already formatted by Cashier (e.g., "$59.00")
                        'status' => ucfirst($invoice->status),
                        'description' => $invoice->lines->data[0]->description ?? 'Subscription Update',
                        'pdf_url' => $invoice->hosted_invoice_url, // Stripe-hosted invoice page
                    ];
                });
            } catch (\Exception $e) {
                // If Stripe API fails, return empty array
                \Log::error('[PaymentHistory] Stripe API Error', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage()
                ]);

                return collect([]);
            }
        });

        return response()->json(['invoices' => $invoices]);
    }
}
