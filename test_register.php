<?php
use App\Models\User;
use App\Enums\UserRole;
use App\Mail\WelcomeVerification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

$email = 'test' . rand(1, 1000) . '@example.com';
$user = User::create([
    'first_name' => 'Test',
    'last_name' => 'User',
    'email' => $email,
    'password' => Hash::make('password'),
    'role' => UserRole::CUSTOMER,
    'is_active' => true,
]);

$verificationUrl = 'http://localhost:3000/verify';
Mail::to($user)->send(new WelcomeVerification($user, $verificationUrl));

echo "User $email created and WelcomeVerification dispatched.\n";
