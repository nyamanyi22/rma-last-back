<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Enums\UserRole;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Mail\WelcomeVerification;
use App\Mail\PasswordResetMail;
use App\Mail\TwoFactorCodeMail;
use App\Models\Setting;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'phone' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:2',
            'address' => 'nullable|string|max:500',
            'city' => 'nullable|string|max:255',
            'postal_code' => 'nullable|string|max:20',
        ]);

        $user = User::create([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => UserRole::CUSTOMER,
            'is_active' => true,
            'phone' => $request->phone,
            'country' => $request->country,
            'address' => $request->address,
            'city' => $request->city,
            'postal_code' => $request->postal_code,
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        // Send Welcome/Verification Email
        $verificationToken = Str::random(64);
        $user->update(['verification_token' => $verificationToken]);

        // 🔧 FOR DEVELOPMENT: Auto-verify in local env so login works immediately, 
        // but we STILL send the email so the user can test the email flow.
        /*if (app()->environment('local')) {
            $user->email_verified_at = now();
            $user->save();
        }*/

        $verificationUrl = config('app.frontend_url', 'http://localhost:3000') . '/verify-email?token=' . $verificationToken . '&email=' . urlencode($user->email);

        Mail::to($user)->send(new WelcomeVerification($user, $verificationUrl));
        \Log::info('Verification email attempted for: ' . $user->email);

        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $user,
        ]);
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        // Check if email is verified
        if (!$user->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Please verify your email before logging in.',
                'email' => $user->email,
                'unverified' => true
            ], 403);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $user,
        ]);
    }

    public function staffLogin(Request $request)
    {
        $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if ($user->role === UserRole::CUSTOMER) {
            throw ValidationException::withMessages([
                'email' => ['Customer accounts cannot log in through the staff portal.'],
            ]);
        }

        // Check if email is verified
        if (!$user->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Please verify your email before logging in.',
                'email' => $user->email,
                'unverified' => true
            ], 403);
        }

        // Check if 2FA is enforced globally
        $is2FAEnforced = Setting::where('key', 'admin_2fa_enforced')->value('value') === '1';

        if ($is2FAEnforced) {
            $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $user->two_factor_code = Hash::make($code);
            $user->two_factor_expires_at = now()->addMinutes(10);
            $user->save();

            Mail::to($user)->send(new TwoFactorCodeMail($user, $code));

            return response()->json([
                'requires_2fa' => true,
                'email' => $user->email,
                'message' => 'Please enter the 6-digit verification code sent to your email.'
            ]);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $user,
        ]);
    }

    public function verify2FA(Request $request)
    {
        $request->validate([
            'email' => 'required|email|string',
            'code' => 'required|string|size:6',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json(['message' => 'User not found.'], 404);
        }

        if (!$user->two_factor_code || empty($user->two_factor_code)) {
            return response()->json(['message' => '2FA is not active for this user.'], 400);
        }

        if (now()->greaterThan($user->two_factor_expires_at)) {
            return response()->json(['message' => 'Verification code has expired. Please request a new one.'], 400);
        }

        if (!Hash::check($request->code, $user->two_factor_code)) {
            return response()->json(['message' => 'Invalid verification code.'], 400);
        }

        // Successfully verified
        $user->two_factor_code = null;
        $user->two_factor_expires_at = null;
        $user->save();

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $user,
        ]);
    }

    public function resend2FA(Request $request)
    {
        $request->validate(['email' => 'required|email|string']);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json(['message' => 'User not found.'], 404);
        }

        // Generate new code and reset expiration
        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $user->two_factor_code = Hash::make($code);
        $user->two_factor_expires_at = now()->addMinutes(10);
        $user->save();

        Mail::to($user)->send(new TwoFactorCodeMail($user, $code));

        return response()->json([
            'success' => true,
            'message' => 'A new verification code has been sent to your email.'
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Successfully logged out',
        ]);
    }

    public function me(Request $request)
    {
        return response()->json($request->user());
    }

    public function forgotPassword(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            // We return success even if user not found for security (probing)
            return response()->json(['message' => 'Reset link sent if account exists.']);
        }

        $token = Str::random(64);

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $user->email],
            ['token' => Hash::make($token), 'created_at' => now()]
        );

        $resetUrl = config('app.frontend_url', 'http://localhost:3000') . '/reset-password?token=' . $token . '&email=' . urlencode($user->email);

        Mail::to($user)->send(new PasswordResetMail($user, $resetUrl));

        return response()->json(['message' => 'Password reset link sent to your email.']);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'token' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $reset = DB::table('password_reset_tokens')->where('email', $request->email)->first();

        if (!$reset || !Hash::check($request->token, $reset->token)) {
            return response()->json(['message' => 'Invalid token or email.'], 422);
        }

        // Check if token expired (e.g. 60 mins)
        if (now()->subMinutes(60)->gt($reset->created_at)) {
            return response()->json(['message' => 'Token has expired.'], 422);
        }

        $user = User::where('email', $request->email)->first();
        if ($user) {
            $user->password = Hash::make($request->password);
            $user->save();

            DB::table('password_reset_tokens')->where('email', $request->email)->delete();

            return response()->json(['message' => 'Password has been reset successfully.']);
        }

        return response()->json(['message' => 'User not found.'], 404);
    }

    public function verify(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'token' => 'required|string',
        ]);

        $user = User::where('email', $request->email)
            ->where('verification_token', $request->token)
            ->first();

        if (!$user) {
            return response()->json(['message' => 'Invalid verification link or email.'], 422);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => 'Email already verified.']);
        }

        $user->email_verified_at = now();
        $user->verification_token = null;
        $user->save();

        return response()->json(['message' => 'Email verified successfully.']);
    }

    public function resendVerificationEmail(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json(['message' => 'Account not found.'], 404);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => 'Email already verified.']);
        }

        $verificationToken = Str::random(64);
        $user->update(['verification_token' => $verificationToken]);

        $verificationUrl = config('app.frontend_url', 'http://localhost:3000') . '/verify-email?token=' . $verificationToken . '&email=' . urlencode($user->email);

        Mail::to($user)->send(new WelcomeVerification($user, $verificationUrl));

        return response()->json(['message' => 'Verification email resent.']);
    }
}
