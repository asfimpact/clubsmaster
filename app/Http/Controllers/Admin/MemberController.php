<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class MemberController extends Controller
{
    /**
     * Display a listing of the members (clients) with filtering and search.
     */
    public function index(Request $request)
    {
        $query = User::where('role', 'client')->with(['subscription.plan']);

        // Search Query
        if ($request->q) {
            $query->where(function ($q) use ($request) {
                $q->where('first_name', 'like', "%{$request->q}%")
                    ->orWhere('last_name', 'like', "%{$request->q}%")
                    ->orWhere('email', 'like', "%{$request->q}%");
            });
        }

        // Filter by Status
        if ($request->status) {
            // Since status is now computed on the front/via appends for logic, 
            // but the database still has a 'status' column (suspended vs active).
            // We search based on the raw status if it's 'suspended'. 
            // For Pending/Inactive, we'd need to check verified_at etc.
            if ($request->status === 'suspended') {
                $query->where('status', 'suspended');
            } elseif ($request->status === 'pending') {
                $query->whereNull('email_verified_at');
            } elseif ($request->status === 'active') {
                $query->whereNotNull('email_verified_at')->where('status', 'active');
            }
        }

        // Filter by Plan
        if ($request->plan) {
            $query->whereHas('subscription.plan', function ($q) use ($request) {
                $q->where('name', $request->plan);
            });
        }

        $members = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'users' => $members,
            'totalUsers' => $members->count()
        ]);
    }

    /**
     * Update the specified member's status.
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:active,suspended',
        ]);

        $user = User::where('role', 'client')->findOrFail($id);
        $user->status = $request->status;
        $user->save();

        return response()->json([
            'message' => 'Member status updated successfully',
            'user' => $user
        ]);
    }

    /**
     * Remove the specified member(s).
     */
    public function destroy(Request $request, $id)
    {
        if ($id === 'bulk') {
            $ids = $request->ids;
            User::whereIn('id', $ids)->where('role', 'client')->delete();
            return response()->json(['message' => 'Members deleted successfully']);
        }

        $user = User::where('role', 'client')->findOrFail($id);
        $user->delete();

        return response()->json(['message' => 'Member deleted successfully']);
    }
}
