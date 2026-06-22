<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kode OTP Reset Password</title>
</head>
<body style="margin:0; padding:0; background-color:#0F2E29; font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color:#0F2E29; padding:48px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="520" cellspacing="0" cellpadding="0" style="background-color:#ffffff; border-radius:28px; overflow:hidden; box-shadow:0 8px 40px rgba(0,0,0,0.25);">
                    <tr>
                        <td style="background: linear-gradient(145deg, #15423C 0%, #1A534B 50%, #2D6E65 100%); padding:48px 40px 40px; text-align:center; position:relative;">
                            <div style="position:absolute; top:-30px; right:-30px; width:120px; height:120px; border-radius:50%; background:rgba(255,255,255,0.04);"></div>
                            <div style="position:absolute; bottom:-20px; left:-20px; width:80px; height:80px; border-radius:50%; background:rgba(255,255,255,0.03);"></div>
                            <div style="background-color:#ffffff; border-radius:20px; padding:18px 36px; display:inline-block; margin-bottom:24px; box-shadow:0 4px 16px rgba(0,0,0,0.15);">
                                <img src="{{ $logoUrl }}" alt="Zad Apps" style="width:160px; height:auto; display:block;" />
                            </div>
                            <h1 style="color:#ffffff; font-size:24px; font-weight:800; margin:0 0 10px; letter-spacing:-0.5px;">
                                Lupa Password
                            </h1>
                            <p style="color:rgba(255,255,255,0.75); font-size:14px; margin:0; line-height:1.6;">
                                Reset password akun Anda dengan kode di bawah ini
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:40px 40px 32px;">
                            <p style="color:#1F2937; font-size:15px; line-height:1.8; margin:0 0 8px;">
                                Halo pelanggan setia <strong style="color:#15423C;">Zad Apps</strong>!
                            </p>
                            <p style="color:#6B7280; font-size:14px; line-height:1.7; margin:0 0 28px;">
                                Kami menerima permintaan untuk mereset password akun Anda. Silakan masukkan kode OTP berikut:
                            </p>
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                                <tr>
                                    <td style="border-radius:20px; overflow:hidden;">
                                        <div style="background: linear-gradient(135deg, #15423C 0%, #1A534B 40%, #2D6E65 100%); border-radius:20px; padding:32px 28px; text-align:center; position:relative;">
                                            <div style="position:absolute; top:0; left:0; right:0; bottom:0; background:repeating-linear-gradient(45deg, transparent, transparent 20px, rgba(255,255,255,0.02) 20px, rgba(255,255,255,0.02) 40px); border-radius:20px;"></div>
                                            <p style="position:relative; color:rgba(255,255,255,0.5); font-size:11px; font-weight:700; letter-spacing:4px; text-transform:uppercase; margin:0 0 16px;">
                                                &#128274; Kode OTP Reset
                                            </p>
                                            <p style="position:relative; color:#ffffff; font-size:44px; font-weight:800; letter-spacing:14px; margin:0; font-family:'Courier New',Courier,monospace; text-shadow:0 2px 8px rgba(0,0,0,0.2);">
                                                {{ $otp }}
                                            </p>
                                        </div>
                                    </td>
                                </tr>
                            </table>
                            <div style="height:24px;"></div>
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color:#F0FDF4; border:1px solid #BBF7D0; border-radius:16px; margin-bottom:16px;">
                                <tr>
                                    <td style="padding:18px 20px;">
                                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                                            <tr>
                                                <td width="40" valign="top" style="padding-right:14px;">
                                                    <div style="background: linear-gradient(135deg, #22C55E, #16A34A); border-radius:12px; width:36px; height:36px; text-align:center; line-height:36px; color:#ffffff; font-size:16px; font-weight:bold;">
                                                        &#9200;
                                                    </div>
                                                </td>
                                                <td valign="top">
                                                    <p style="color:#15803D; font-size:13px; font-weight:700; margin:0 0 4px;">
                                                        Kode berlaku 10 menit
                                                    </p>
                                                    <p style="color:#166534; font-size:12px; line-height:1.5; margin:0;">
                                                        Segera masukkan kode sebelum masa berlaku habis.
                                                    </p>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color:#FFF7ED; border:1px solid #FED7AA; border-radius:16px;">
                                <tr>
                                    <td style="padding:18px 20px;">
                                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                                            <tr>
                                                <td width="40" valign="top" style="padding-right:14px;">
                                                    <div style="background: linear-gradient(135deg, #F59E0B, #D97706); border-radius:12px; width:36px; height:36px; text-align:center; line-height:36px; color:#ffffff; font-size:18px; font-weight:bold;">
                                                        &#9888;
                                                    </div>
                                                </td>
                                                <td valign="top">
                                                    <p style="color:#92400E; font-size:13px; font-weight:700; margin:0 0 4px;">
                                                        Jaga kerahasiaan
                                                    </p>
                                                    <p style="color:#78350F; font-size:12px; line-height:1.5; margin:0;">
                                                        Jangan berikan kode ini kepada siapapun, termasuk pihak Zad Apps.
                                                    </p>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:0 40px;">
                            <div style="height:1px; background: linear-gradient(90deg, transparent, #E5E7EB, transparent);"></div>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:24px 40px;">
                            <p style="color:#9CA3AF; font-size:12px; line-height:1.7; margin:0; text-align:center;">
                                Jika Anda tidak meminta reset password, abaikan email ini dan pastikan password Anda aman.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="background-color:#123833; padding:28px 40px; text-align:center;">
                            <p style="color:rgba(255,255,255,0.7); font-size:13px; font-weight:600; margin:0 0 6px;">
                                Zad Apps &mdash; Lifestyle &amp; Care
                            </p>
                            <p style="color:rgba(255,255,255,0.35); font-size:11px; margin:0 0 4px;">
                                &copy; {{ date('Y') }} Zad Apps. All rights reserved.
                            </p>
                            <p style="color:rgba(255,255,255,0.25); font-size:10px; margin:0;">
                                Email ini dikirim otomatis, mohon tidak membalas.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
