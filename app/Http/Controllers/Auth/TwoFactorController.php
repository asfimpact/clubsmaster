<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Exception;

class TwoFactorController extends Controller
{
    /**
     * Resend a 2FA code (Manual trigger from user).
     */
    public function send(Request $request)
    {
        $user = Auth::user();
        $code = rand(100000, 999999);

        $user->update([
            'two_factor_code' => $code,
            'two_factor_expires_at' => now()->addMinutes(15),
        ]);

        $settings = DB::table('settings')->pluck('value', 'key');

        try {
            Config::set('mail.default', 'smtp');
            Config::set('mail.mailers.smtp.host', $settings['mail_host'] ?? '');
            Config::set('mail.mailers.smtp.port', $settings['mail_port'] ?? '587');
            Config::set('mail.mailers.smtp.username', $settings['mail_username'] ?? '');
            Config::set('mail.mailers.smtp.password', $settings['mail_password'] ?? '');
            Config::set('mail.mailers.smtp.encryption', $settings['mail_encryption'] ?? 'tls');
            Config::set('mail.from.address', $settings['mail_from_address'] ?? '');
            Config::set('mail.from.name', $settings['mail_from_name'] ?? 'ClubMaster');

            Mail::raw("Your ClubMaster security code is: {$code}. This code will expire in 10 minutes.", function ($message) use ($user) {
                $message->to($user->email)->subject('New Verification Code');
            });

            return response()->json(['message' => 'New code sent.']);
        } catch (Exception $e) {
            return response()->json(['message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Verify the provided 2FA code.
     */
    public function verify(Request $request)
    {
        $request->validate(['code' => 'required|string|size:6']);
        $user = Auth::user()->fresh();

        // 🛡️ Standard comparison
        $isMatch = ($user->two_factor_code == $request->code);
        $isNotExpired = ($user->two_factor_expires_at && now()->lt($user->two_factor_expires_at));

        if ($isMatch && $isNotExpired) {
            $user->update([
                'two_factor_verified_at' => now(),
                'email_verified_at' => $user->email_verified_at ?? now(),
                'two_factor_code' => null,
                'two_factor_expires_at' => null,
            ]);

            return response()->json([
                'message' => 'Verified.',
                'userData' => [
                    'id' => $user->id,
                    'fullName' => $user->first_name . ' ' . $user->last_name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'status' => $user->computed_status,
                ]
            ]);
        }

        return response()->json([
            'message' => 'Invalid or expired code.',
            'debug' => [
                'user_id' => $user->id,
                'sent_code' => $request->code,
                'stored_code' => $user->two_factor_code,
                'is_match' => $isMatch,
                'is_not_expired' => $isNotExpired,
                'expires_at' => $user->two_factor_expires_at ? $user->two_factor_expires_at->toDateTimeString() : 'null',
                'now' => now()->toDateTimeString(),
            ]
        ], 422);
    }
}
