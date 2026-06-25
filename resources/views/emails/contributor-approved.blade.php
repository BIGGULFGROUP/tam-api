<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contributor Application Approved</title>
</head>
<body style="margin:0;padding:0;background-color:#f4f4f7;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f4f7;padding:40px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="background-color:#ffffff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.06);">
                    <!-- Header -->
                    <tr>
                        <td style="background-color:#1a1a2e;padding:32px 40px;text-align:center;">
                            <h1 style="color:#ffffff;font-size:24px;font-weight:700;margin:0;">🎉 You're In!</h1>
                        </td>
                    </tr>
                    <!-- Body -->
                    <tr>
                        <td style="padding:40px;">
                            <p style="color:#333333;font-size:16px;line-height:1.6;margin:0 0 16px;">
                                Hi {{ $fullName }},
                            </p>
                            <p style="color:#333333;font-size:16px;line-height:1.6;margin:0 0 16px;">
                                Great news — your contributor application has been <strong style="color:#10b981;">approved</strong>! We're excited to have you on board.
                            </p>

                            <!-- Credentials Box -->
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f8f9fb;border:1px solid #e5e7eb;border-radius:6px;margin:24px 0;">
                                <tr>
                                    <td style="padding:24px;">
                                        <h2 style="color:#1a1a2e;font-size:16px;font-weight:600;margin:0 0 16px;">Your Login Credentials</h2>
                                        <table role="presentation" cellpadding="0" cellspacing="0">
                                            <tr>
                                                <td style="padding:4px 0;color:#6b7280;font-size:14px;width:80px;">Email:</td>
                                                <td style="padding:4px 0;color:#1a1a2e;font-size:14px;font-weight:500;">{{ $email }}</td>
                                            </tr>
                                            <tr>
                                                <td style="padding:4px 0;color:#6b7280;font-size:14px;width:80px;">Password:</td>
                                                <td style="padding:4px 0;color:#1a1a2e;font-size:14px;font-weight:500;">{{ $password }}</td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>

                            <!-- CTA Button -->
                            <table role="presentation" align="center" cellpadding="0" cellspacing="0" style="margin:32px auto;">
                                <tr>
                                    <td align="center" style="background-color:#e94560;border-radius:6px;">
                                        <a href="{{ $loginUrl }}" target="_blank" rel="noopener" style="display:inline-block;padding:14px 36px;color:#ffffff;font-size:16px;font-weight:600;text-decoration:none;letter-spacing:0.3px;">
                                            Go to Login
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            <p style="color:#6b7280;font-size:14px;line-height:1.5;margin:24px 0 0;">
                                For security, we recommend changing your password after your first login. If you have any questions, just reply to this email — we're happy to help.
                            </p>
                        </td>
                    </tr>
                    <!-- Footer -->
                    <tr>
                        <td style="background-color:#f8f9fb;padding:20px 40px;text-align:center;border-top:1px solid #e5e7eb;">
                            <p style="color:#9ca3af;font-size:12px;margin:0;">
                                &copy; {{ date('Y') }} The African Mail. All rights reserved.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
