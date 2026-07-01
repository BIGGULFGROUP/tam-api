<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to The African Mail</title>
</head>
<body style="margin:0;padding:0;background-color:#f0ece3;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;">

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f0ece3;padding:40px 16px;">
  <tr>
    <td align="center">
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;background-color:#ffffff;overflow:hidden;">

        <!-- ── Header / Hero ── -->
        <tr>
          <td style="background-color:#030d14;padding:36px 40px 32px;">
            <!-- Logo wordmark text -->
            <p style="margin:0 0 28px;font-size:13px;font-weight:800;letter-spacing:3px;color:#FFCB2C;text-transform:uppercase;">THE AFRICAN MAIL</p>

            <h1 style="margin:0 0 10px;font-size:28px;font-weight:900;color:#ffffff;line-height:1.15;letter-spacing:-0.5px;">
              Welcome to Africa's<br>story, told right.
            </h1>
            <p style="margin:0;font-size:15px;color:rgba(255,255,255,0.55);line-height:1.55;">
              Your account is confirmed. You're now part of an editorial community telling Africa's story with depth and truth.
            </p>
          </td>
        </tr>

        <!-- ── Gold accent bar ── -->
        <tr>
          <td style="height:4px;background-color:#FFCB2C;"></td>
        </tr>

        <!-- ── Body ── -->
        <tr>
          <td style="padding:36px 40px 28px;">
            <p style="margin:0 0 20px;font-size:16px;color:#1a1a1a;line-height:1.6;">
              Hi {{ $displayName }},
            </p>
            <p style="margin:0 0 20px;font-size:15px;color:#444444;line-height:1.65;">
              Thank you for joining <strong style="color:#030d14;">The African Mail</strong>. You now have full access to deep analysis, original reporting, and multimedia content across every major beat — from geopolitics and business to film, sport, and culture.
            </p>

            @if($settings->welcome_email_body_extra)
            <p style="margin:0 0 20px;font-size:15px;color:#444444;line-height:1.65;">
              {{ $settings->welcome_email_body_extra }}
            </p>
            @endif

            <!-- CTA -->
            <table role="presentation" cellpadding="0" cellspacing="0" style="margin:28px 0;">
              <tr>
                <td style="background-color:#030d14;">
                  <a href="{{ \App\Support\PublicUrl::to('/') }}"
                     target="_blank" rel="noopener"
                     style="display:inline-block;padding:14px 32px;color:#FFCB2C;font-size:14px;font-weight:700;text-decoration:none;letter-spacing:0.5px;text-transform:uppercase;">
                    Start Reading →
                  </a>
                </td>
              </tr>
            </table>

            <!-- What you can do -->
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
                   style="border:1px solid #e8e2d5;background-color:#FFFBF2;margin:0 0 28px;">
              <tr>
                <td style="padding:22px 24px;">
                  <p style="margin:0 0 14px;font-size:11px;font-weight:800;letter-spacing:1.5px;color:#FFCB2C;text-transform:uppercase;">
                    What's next
                  </p>
                  <table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;">
                    <tr>
                      <td style="padding:7px 0;font-size:14px;color:#333;border-bottom:1px solid #e8e2d5;">
                        📖 &nbsp; Read deep analysis across 10 categories
                      </td>
                    </tr>
                    <tr>
                      <td style="padding:7px 0;font-size:14px;color:#333;border-bottom:1px solid #e8e2d5;">
                        🎬 &nbsp; Watch original video content &amp; shorts
                      </td>
                    </tr>
                    <tr>
                      <td style="padding:7px 0;font-size:14px;color:#333;border-bottom:1px solid #e8e2d5;">
                        💾 &nbsp; Save articles to your personal library
                      </td>
                    </tr>
                    <tr>
                      <td style="padding:7px 0;font-size:14px;color:#333;">
                        ✍️ &nbsp; Apply to become a contributor
                      </td>
                    </tr>
                  </table>
                </td>
              </tr>
            </table>
          </td>
        </tr>

        @if($settings->app_store_url || $settings->play_store_url)
        <!-- ── Get the App ── -->
        <tr>
          <td style="background-color:#030d14;padding:28px 40px;">
            <p style="margin:0 0 6px;font-size:11px;font-weight:800;letter-spacing:1.5px;color:#FFCB2C;text-transform:uppercase;">
              Get the App
            </p>
            <p style="margin:0 0 20px;font-size:14px;color:rgba(255,255,255,0.55);line-height:1.5;">
              Take The African Mail wherever you go. Download the app for a seamless mobile reading &amp; watch experience.
            </p>
            <table role="presentation" cellpadding="0" cellspacing="0">
              <tr>
                @if($settings->app_store_url)
                <td style="padding-right:12px;">
                  <a href="{{ $settings->app_store_url }}" target="_blank" rel="noopener"
                     style="display:inline-block;background-color:#000000;color:#ffffff;text-decoration:none;padding:10px 18px;font-size:12px;font-weight:600;letter-spacing:0.2px;">
                    <span style="font-size:9px;display:block;color:rgba(255,255,255,0.6);text-transform:uppercase;letter-spacing:1px;">Download on the</span>
                    🍎 &nbsp;App Store
                  </a>
                </td>
                @endif
                @if($settings->play_store_url)
                <td>
                  <a href="{{ $settings->play_store_url }}" target="_blank" rel="noopener"
                     style="display:inline-block;background-color:#000000;color:#ffffff;text-decoration:none;padding:10px 18px;font-size:12px;font-weight:600;letter-spacing:0.2px;">
                    <span style="font-size:9px;display:block;color:rgba(255,255,255,0.6);text-transform:uppercase;letter-spacing:1px;">Get it on</span>
                    ▶ &nbsp;Google Play
                  </a>
                </td>
                @endif
              </tr>
            </table>
          </td>
        </tr>
        @endif

        <!-- ── Footer ── -->
        <tr>
          <td style="padding:20px 40px;background-color:#f0ece3;border-top:1px solid #e8e2d5;text-align:center;">
            <p style="margin:0 0 8px;font-size:11px;font-weight:700;letter-spacing:2px;color:#030d14;text-transform:uppercase;">
              The African Mail
            </p>
            <p style="margin:0 0 10px;font-size:11px;color:#999999;">
              Africa's story, told right.
            </p>
            <p style="margin:0;font-size:11px;color:#aaaaaa;">
              You received this because you created an account.
              <a href="{{ \App\Support\PublicUrl::to('/account/settings') }}"
                 style="color:#aaaaaa;">Manage preferences</a>
              &nbsp;·&nbsp;
              &copy; {{ date('Y') }} The African Mail.
            </p>
          </td>
        </tr>

      </table>
    </td>
  </tr>
</table>

</body>
</html>
