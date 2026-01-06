<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use Illuminate\Http\Request;

class PlanController extends Controller
{
    /**
     * Display a listing of the plans.
     */
    public function index()
    {
        return response()->json(Plan::all());
    }

    /**
     * Store a newly created plan.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:plans,name',
            'tagline' => 'nullable|string|max:255',
            'price' => 'required|numeric|min:0',
            'yearly_price' => 'nullable|numeric|min:0',
            'stripe_monthly_price_id' => 'nullable|string|max:255',
            'stripe_yearly_price_id' => 'nullable|string|max:255',
            'duration_days' => 'required|integer|min:1',
            'yearly_duration_days' => 'nullable|integer|min:1',
            'features' => 'nullable|string', // Expecting new-line separated string
            'is_enabled' => 'nullable|boolean',
        ]);

        $data = $request->all();

        // Process Features
        if ($request->has('features') && !empty($request->features)) {
            // Split by newline, trim, and remove empty entries
            $featuresArray = array_filter(array_map('trim', explode("\n", $request->features)));
            $data['features'] = array_values($featuresArray); // Re-index array
        } else {
            $data['features'] = [];
        }

        $plan = Plan::create($data);

        return response()->json([
            'message' => 'Plan created successfully',
            'plan' => $plan
        ]);
    }

    /**
     * Update the specified plan.
     */
    public function update(Request $request, $id)
    {
        $plan = Plan::findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:255|unique:plans,name,' . $id,
            'tagline' => 'nullable|string|max:255',
            'price' => 'required|numeric|min:0',
            'yearly_price' => 'nullable|numeric|min:0',
            'stripe_monthly_price_id' => 'nullable|string|max:255',
            'stripe_yearly_price_id' => 'nullable|string|max:255',
            'duration_days' => 'required|integer|min:1',
            'yearly_duration_days' => 'nullable|integer|min:1',
            'features' => 'nullable|string',
            'is_enabled' => 'nullable|boolean',
        ]);

        $data = $request->all();

        if ($request->has('features')) { // Only process if sent
            if (!empty($request->features)) {
                $featuresArray = array_filter(array_map('trim', explode("\n", $request->features)));
                $data['features'] = array_values($featuresArray);
            } else {
                $data['features'] = [];
            }
        }

        $plan->update($data);

        return response()->json([
            'message' => 'Plan updated successfully',
            'plan' => $plan
        ]);
    }

    /**
     * Remove the specified plan.
     */
    public function destroy($id)
    {
        $plan = Plan::findOrFail($id);

        // Check if plan has active subscriptions
        if ($plan->subscriptions()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete plan with active subscriptions'
            ], 422);
        }

        $plan->delete();

        return response()->json(['message' => 'Plan deleted successfully']);
    }
}
