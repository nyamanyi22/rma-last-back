<!DOCTYPE html>
<html>
<head>
    <title>Your 2FA Verification Code</title>
</head>
<body style="font-family: Arial, sans-serif; background-color: #f4f4f4; padding: 40px; text-align: center;">
    <div style="max-width: 600px; margin: 0 auto; background: #ffffff; padding: 40px; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.1);">
        <h2 style="color: #333333; margin-bottom: 20px;">Staff Access Verification</h2>
        
        <p style="color: #555555; font-size: 16px; line-height: 1.5;">
            Hello {{ $user->first_name }},<br><br>
            A request to sign into the staff portal was made using your credentials.
            To complete the sign in process, please enter the following verification code:
        </p>

        <div style="background-color: #f8f9fa; border: 1px solid #e9ecef; border-radius: 6px; padding: 20px; margin: 30px 0;">
            <span style="font-size: 32px; font-weight: bold; letter-spacing: 5px; color: #1976d2;">
                {{ $code }}
            </span>
        </div>

        <p style="color: #e53935; font-size: 14px; margin-top: 20px; border-top: 1px solid #eeeeee; padding-top: 20px;">
            <strong>Security Warning:</strong> This code will expire in exactly 10 minutes. Do not share this code with anyone.
        </p>
    </div>
</body>
</html>
