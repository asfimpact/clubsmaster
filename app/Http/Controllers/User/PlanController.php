<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use Illuminate\Http\Request;

class PlanController extends Controller
{
    public function index()
    {
        // Only show enabled plans to users (admins see all plans in admin panel)
        return response()->json(Plan::enabled()->get());
    }
}
