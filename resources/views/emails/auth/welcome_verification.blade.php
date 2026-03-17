<x-mail::message>
# Welcome to {{ config('app.name') }}!

Hi {{ $user->first_name }},

Thank you for joining us! We're excited to have you on board.

To get started, please verify your email address by clicking the button below:

<x-mail::button :url="$verificationUrl">
Verify Email Address
</x-mail::button>

If you did not create an account, no further action is required.

Thanks,<br>
{{ config('app.name') }} Team
</x-mail::message>
