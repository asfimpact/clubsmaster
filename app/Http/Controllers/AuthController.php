<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    /**
     * Register a new user.
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()
            ], 422);
        }

        // Note: Password will be hashed automatically by the User model's 'hashed' cast
        $user = User::create([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'email' => $request->email,
            'password' => $request->password,
            'role' => 'client',
        ]);

        $accessToken = $user->createToken('accessToken')->plainTextToken;

        // Temporary: Give full control to both roles to bypass redirect loops during development
        $userAbilityRules = [
            [
                'action' => 'manage',
                'subject' => 'all',
            ],
        ];

        return response()->json([
            'accessToken' => $accessToken,
            'userData' => [
                'id' => $user->id,
                'fullName' => $user->first_name . ' ' . $user->last_name,
                'email' => $user->email,
                'role' => $user->role,
            ],
            'userAbilityRules' => $userAbilityRules,
        ], 201);
    }

    /**
     * Login user and create token.
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()
            ], 422);
        }

        $credentials = $request->only('email', 'password');

        if (!Auth::attempt($credentials)) {
            return response()->json([
                'errors' => ['email' => ['Invalid email or password']]
            ], 401);
        }

        $user = Auth::user();
        $accessToken = $user->createToken('accessToken')->plainTextToken;

        // Temporary: Give full control to both roles to bypass redirect loops during development
        $userAbilityRules = [
            [
                'action' => 'manage',
                'subject' => 'all',
            ],
        ];

        return response()->json([
            'accessToken' => $accessToken,
            'userData' => [
                'id' => $user->id,
                'fullName' => $user->first_name . ' ' . $user->last_name,
                'email' => $user->email,
                'role' => $user->role,
            ],
            'userAbilityRules' => $userAbilityRules,
        ], 200);
    }
}
