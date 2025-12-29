<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Exception;

class SettingController extends Controller
{
    /**
     * Get all settings.
     */
    public function index()
    {
        $settings = DB::table('settings')->pluck('value', 'key');
        return response()->json($settings);
    }

    /**
     * Update or create a setting.
     */
    public function update(Request $request)
    {
        $request->validate([
            'settings' => 'required|array',
        ]);

        foreach ($request->settings as $key => $value) {
            DB::table('settings')->updateOrInsert(
                ['key' => $key],
                ['value' => (string) ($value ?? ''), 'updated_at' => now()]
            );
        }

        return response()->json(['message' => 'Settings updated successfully']);
    }

    /**
     * Test the email connection by sending a real email using DB settings.
     */
    public function testEmail(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        // 1. Fetch settings from DB
        $settings = DB::table('settings')->pluck('value', 'key');

        // 2. Dynamically apply settings to Laravel Mailer
        try {
            Config::set('mail.default', 'smtp');
            Config::set('mail.mailers.smtp.host', $settings['mail_host'] ?? '');
            Config::set('mail.mailers.smtp.port', $settings['mail_port'] ?? '587');
            Config::set('mail.mailers.smtp.username', $settings['mail_username'] ?? '');
            Config::set('mail.mailers.smtp.password', $settings['mail_password'] ?? '');
            Config::set('mail.mailers.smtp.encryption', $settings['mail_encryption'] ?? 'tls');
            Config::set('mail.from.address', $settings['mail_from_address'] ?? '');
            Config::set('mail.from.name', $settings['mail_from_name'] ?? 'ClubMaster');

            // 3. Send a test raw email
            Mail::raw('This is a test email from your ClubMaster Admin Portal. If you are reading this, your SMTP settings are configured correctly!', function ($message) use ($request, $settings) {
                $message->to($request->email)
                    ->subject('SMTP Connection Test - ClubMaster');
            });

            return response()->json([
                'message' => 'Test email sent successfully! Please check your inbox (and spam folder).'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Mail Error: ' . $e->getMessage()
            ], 500);
        }
    }
}
