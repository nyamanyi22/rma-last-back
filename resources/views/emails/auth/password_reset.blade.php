<x-mail::message>
# Password Reset Request

Hi {{ $user->first_name }},

You are receiving this email because we received a password reset request for your account.

<x-mail::button :url="$resetUrl">
Reset Password
</x-mail::button>

This password reset link will expire in 60 minutes.

If you did not request a password reset, no further action is required.

Thanks,<br>
{{ config('app.name') }} Team
</x-mail::message>
