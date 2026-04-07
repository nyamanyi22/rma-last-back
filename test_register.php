<?php
use App\Models\User;
use App\Enums\UserRole;
use App\Mail\WelcomeVerification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

$email = 'test' . rand(1, 1000) . '@example.com';
$user = User::create([
    'first_name' => 'Test',
    'last_name' => 'User',
    'email' => $email,
    'password' => Hash::make('password'),
    'role' => UserRole::CUSTOMER,
    'is_active' => true,
]);

$verificationToken = Str::random(64);
$user->update(['verification_token' => $verificationToken]);

$verificationUrl = config('app.frontend_url', 'http://localhost:3000')
    . '/verify-email?token=' . $verificationToken
    . '&email=' . urlencode($user->email);

Mail::to($user)->send(new WelcomeVerification($user, $verificationUrl));

echo "User $email created and WelcomeVerification dispatched.\n";
echo "Verification URL: $verificationUrl\n";
