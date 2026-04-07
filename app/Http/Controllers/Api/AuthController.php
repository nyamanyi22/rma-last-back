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
use App\Services\EmailService;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(private readonly EmailService $mailService)
    {
    }

    private function frontendUrl(): string
    {
        return rtrim((string) config('app.frontend_url', env('APP_FRONTEND_URL', 'http://localhost:5173')), '/');
    }

    private function buildVerificationUrl(User $user, string $verificationToken): string
    {
        return $this->frontendUrl() . '/verify-email?token=' . $verificationToken . '&email=' . urlencode($user->email);
    }

    private function buildResetUrl(User $user, string $token): string
    {
        return $this->frontendUrl() . '/reset-password?token=' . $token . '&email=' . urlencode($user->email);
    }

    private function issueAuthToken(User $user, string $tokenName = 'auth_token'): array
    {
        $expiresAt = now()->addHours(Setting::sessionDurationHours());
        $token = $user->createToken($tokenName, ['*'], $expiresAt)->plainTextToken;

        return [
            'token' => $token,
            'expires_at' => $expiresAt->toIso8601String(),
            'duration_hours' => Setting::sessionDurationHours(),
            'timeout_alerts_enabled' => Setting::sessionTimeoutAlertsEnabled(),
        ];
    }

    private function authPayload(User $user, string $tokenName = 'auth_token'): array
    {
        $session = $this->issueAuthToken($user, $tokenName);

        return [
            'access_token' => $session['token'],
            'token_type' => 'Bearer',
            'user' => $user,
            'session_expires_at' => $session['expires_at'],
            'session_duration_hours' => $session['duration_hours'],
            'session_timeout_alerts' => $session['timeout_alerts_enabled'],
        ];
    }

    private function sendMailMessage(User $user, \Illuminate\Contracts\Mail\Mailable $mailable, string $context): bool
    {
        $emailType = match ($context) {
            'register_verification', 'resend_verification' => EmailService::EMAIL_VERIFICATION,
            'staff_login_2fa', 'resend_2fa' => EmailService::TWO_FACTOR,
            'forgot_password' => EmailService::PASSWORD_RESET,
            default => EmailService::CUSTOMER_RMA_SUBMITTED,
        };

        return $this->mailService->send($user, $mailable, $emailType, $context, [
            'email' => $user->email,
        ]);
    }

    public function register(Request $request)
    {
        if (!Setting::boolean('allow_registrations', true)) {
            return response()->json([
                'message' => 'New registrations are currently disabled.',
            ], 403);
        }

        $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:' . Setting::minPasswordLength() . '|confirmed',
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
            'password_changed_at' => now(),
            'role' => UserRole::CUSTOMER,
            'is_active' => true,
            'phone' => $request->phone,
            'country' => $request->country,
            'address' => $request->address,
            'city' => $request->city,
            'postal_code' => $request->postal_code,
        ]);

        // Send Welcome/Verification Email
        $verificationToken = Str::random(64);
        $user->update(['verification_token' => $verificationToken]);

        // 🔧 FOR DEVELOPMENT: Auto-verify in local env so login works immediately, 
        // but we STILL send the email so the user can test the email flow.
        /*if (app()->environment('local')) {
            $user->email_verified_at = now();
            $user->save();
        }*/

        $verificationUrl = $this->buildVerificationUrl($user, $verificationToken);
        $mailSent = $this->sendMailMessage($user, new WelcomeVerification($user, $verificationUrl), 'register_verification');

        return response()->json([
            ...$this->authPayload($user, 'auth_token'),
            'verification_email_sent' => $mailSent,
            'message' => $mailSent
                ? 'Registration successful. Verification email sent.'
                : 'Registration successful, but the verification email could not be sent. Please try resend verification.',
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

        return response()->json($this->authPayload($user, 'auth_token'));
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
        $is2FAEnforced = Setting::boolean('two_factor_required', false);

        if ($is2FAEnforced) {
            $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $user->two_factor_code = Hash::make($code);
            $user->two_factor_expires_at = now()->addMinutes(10);
            $user->save();

            $mailSent = $this->sendMailMessage($user, new TwoFactorCodeMail($user, $code), 'staff_login_2fa');

            if (!$mailSent) {
                return response()->json([
                    'message' => 'We could not send the 2FA verification code email. Please try again.',
                ], 500);
            }

            return response()->json([
                'requires_2fa' => true,
                'email' => $user->email,
                'message' => 'Please enter the 6-digit verification code sent to your email.'
            ]);
        }

        return response()->json($this->authPayload($user, 'auth_token'));
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

        return response()->json($this->authPayload($user, 'auth_token'));
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

        $mailSent = $this->sendMailMessage($user, new TwoFactorCodeMail($user, $code), 'resend_2fa');

        if (!$mailSent) {
            return response()->json([
                'success' => false,
                'message' => 'We could not send a new verification code email. Please try again.',
            ], 500);
        }

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
        return response()->json([
            'user' => $request->user(),
            'session_timeout_alerts' => Setting::sessionTimeoutAlertsEnabled(),
            'session_duration_hours' => Setting::sessionDurationHours(),
            'session_expires_at' => optional($request->user()->currentAccessToken()?->expires_at)?->toIso8601String(),
        ]);
    }

    public function refreshSession(Request $request)
    {
        $user = $request->user();
        $currentToken = $user?->currentAccessToken();

        if (!$user || !$currentToken) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $currentToken->delete();

        return response()->json([
            'success' => true,
            'message' => 'Session refreshed successfully.',
            ...$this->authPayload($user, 'auth_token'),
        ]);
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

        $resetUrl = $this->buildResetUrl($user, $token);
        $mailSent = $this->sendMailMessage($user, new PasswordResetMail($user, $resetUrl), 'forgot_password');

        return response()->json([
            'message' => $mailSent
                ? 'Password reset link sent to your email.'
                : 'We could not send the password reset email. Please try again.',
            'email_sent' => $mailSent,
        ], $mailSent ? 200 : 500);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'token' => 'required|string',
            'password' => 'required|string|min:' . Setting::minPasswordLength() . '|confirmed',
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
            $user->password_changed_at = now();
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

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json(['message' => 'Invalid verification link or email.'], 422);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Email already verified.',
                'already_verified' => true,
            ]);
        }

        if ($user->verification_token !== $request->token) {
            return response()->json(['message' => 'Invalid verification link or email.'], 422);
        }

        $user->email_verified_at = now();
        $user->verification_token = null;
        $user->save();

        return response()->json([
            'message' => 'Email verified successfully.',
            'already_verified' => false,
        ]);
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

        $verificationUrl = $this->buildVerificationUrl($user, $verificationToken);
        $mailSent = $this->sendMailMessage($user, new WelcomeVerification($user, $verificationUrl), 'resend_verification');

        return response()->json([
            'message' => $mailSent
                ? 'Verification email resent.'
                : 'We could not resend the verification email. Please try again.',
            'email_sent' => $mailSent,
        ], $mailSent ? 200 : 500);
    }
}
