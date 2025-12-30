<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Exception;

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
            'phone' => 'required|string|max:20',
            'password' => 'required|string|min:8',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::create([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'email' => $request->email,
            'phone' => $request->phone,
            'password' => $request->password,
            'role' => 'client',
        ]);

        // Auto-trigger 2FA if enabled
        $this->trigger2FA($user);

        $accessToken = $user->createToken('accessToken')->plainTextToken;

        return response()->json([
            'accessToken' => $accessToken,
            'userData' => [
                'id' => $user->id,
                'fullName' => $user->first_name . ' ' . $user->last_name,
                'mobile' => $user->phone, // Standardized key for UI
                'email' => $user->email,
                'role' => $user->role,
                'status' => $user->computed_status,
            ],
            'userAbilityRules' => $this->getAbilityRules($user),
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
            return response()->json(['errors' => $validator->errors()], 422);
        }

        if (!Auth::attempt($request->only('email', 'password'))) {
            return response()->json(['errors' => ['email' => ['Invalid email or password']]], 401);
        }

        $user = Auth::user();

        // Auto-trigger 2FA if status is Inactive or Pending
        if ($user->computed_status !== 'Active') {
            $this->trigger2FA($user);
        }

        $accessToken = $user->createToken('accessToken')->plainTextToken;

        return response()->json([
            'accessToken' => $accessToken,
            'userData' => [
                'id' => $user->id,
                'fullName' => $user->first_name . ' ' . $user->last_name,
                'mobile' => $user->phone,
                'email' => $user->email,
                'role' => $user->role,
                'status' => $user->computed_status,
            ],
            'userAbilityRules' => $this->getAbilityRules($user),
        ], 200);
    }

    /**
     * Helper to send 2FA codes during Auth flow.
     */
    private function trigger2FA($user)
    {
        // 1. Generate code
        $code = rand(100000, 999999);
        $user->update([
            'two_factor_code' => $code,
            'two_factor_expires_at' => now()->addMinutes(15),
        ]);

        // 2. Load SMTP from DB
        $settings = DB::table('settings')->pluck('value', 'key');
        if (empty($settings['mail_host']))
            return;

        try {
            Config::set('mail.default', 'smtp');
            Config::set('mail.mailers.smtp.host', $settings['mail_host']);
            Config::set('mail.mailers.smtp.port', $settings['mail_port']);
            Config::set('mail.mailers.smtp.username', $settings['mail_username']);
            Config::set('mail.mailers.smtp.password', $settings['mail_password']);
            Config::set('mail.mailers.smtp.encryption', $settings['mail_encryption']);
            Config::set('mail.from.address', $settings['mail_from_address']);
            Config::set('mail.from.name', $settings['mail_from_name']);

            Mail::raw("Your ClubMaster security code is: {$code}. This code will expire in 10 minutes.", function ($message) use ($user) {
                $message->to($user->email)->subject('Verification Code');
            });
        } catch (Exception $e) {
            \Log::error("2FA Send Error: " . $e->getMessage());
        }
    }

    private function getAbilityRules($user)
    {
        return $user->role === 'admin'
            ? [['action' => 'manage', 'subject' => 'all']]
            : [['action' => 'read', 'subject' => 'AclDemo']];
    }

    /**
     * Update user profile.
     */
    public function updateProfile(Request $request)
    {
        $user = Auth::user();

        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user->update([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'phone' => $request->phone,
        ]);

        return response()->json([
            'message' => 'Profile updated successfully.',
            'userData' => [
                'id' => $user->id,
                'fullName' => $user->first_name . ' ' . $user->last_name,
                'mobile' => $user->phone,
                'email' => $user->email,
                'role' => $user->role,
                'status' => $user->computed_status,
            ],
        ]);
    }

    /**
     * Change password.
     */
    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        $user = Auth::user();

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'message' => 'The provided current password does not match your current password.'
            ], 422);
        }

        $user->update([
            'password' => Hash::make($request->new_password)
        ]);

        return response()->json([
            'message' => 'Password changed successfully.'
        ]);
    }
}
