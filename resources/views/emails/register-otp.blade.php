<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kode OTP Pendaftaran</title>
</head>
<body style="margin:0; padding:0; background-color:#F0ECE4; font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color:#F0ECE4; padding:40px 20px;">
        <tr>
            <td align="center">
                <table role="presentation" width="480" cellspacing="0" cellpadding="0" style="background-color:#ffffff; border-radius:24px; overflow:hidden; box-shadow:0 4px 24px rgba(0,0,0,0.08);">
                    
                    <!-- Header Hijau Gelap -->
                    <tr>
                        <td style="background-color:#15423C; padding:40px 40px 32px; text-align:center;">
                            <!-- Logo -->
                            <div style="background-color:#ffffff; border-radius:16px; padding:16px 32px; display:inline-block; margin-bottom:20px;">
                                <img src="{{ $logoUrl }}" alt="Zad Apps" style="width:160px; height:auto; display:block;" />
                            </div>
                            <h1 style="color:#ffffff; font-size:22px; font-weight:700; margin:0 0 8px; letter-spacing:-0.5px;">
                                Verifikasi Pendaftaran
                            </h1>
                            <p style="color:rgba(255,255,255,0.7); font-size:14px; margin:0; line-height:1.5;">
                                Gunakan kode di bawah untuk menyelesaikan pendaftaran Anda
                            </p>
                        </td>
                    </tr>
                    
                    <!-- Body Content -->
                    <tr>
                        <td style="padding:40px;">
                            <p style="color:#374151; font-size:15px; line-height:1.7; margin:0 0 24px;">
                                Halo! Terima kasih telah mendaftar di <strong style="color:#15423C;">Zad Apps</strong>. 
                                Berikut adalah kode verifikasi OTP Anda:
                            </p>
                            
                            <!-- OTP Code Box -->
                            <div style="background: linear-gradient(135deg, #15423C 0%, #1A534B 100%); border-radius:16px; padding:28px; text-align:center; margin:0 0 28px;">
                                <p style="color:rgba(255,255,255,0.6); font-size:11px; font-weight:700; letter-spacing:3px; text-transform:uppercase; margin:0 0 12px;">
                                    KODE OTP ANDA
                                </p>
                                <p style="color:#ffffff; font-size:40px; font-weight:800; letter-spacing:12px; margin:0; font-family:'Courier New',Courier,monospace;">
                                    {{ $otp }}
                                </p>
                            </div>
                            
                            <!-- Info Box -->
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color:#F0FDF4; border:1px solid #BBF7D0; border-radius:12px; margin-bottom:24px;">
                                <tr>
                                    <td style="padding:16px 20px;">
                                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                                            <tr>
                                                <td width="32" valign="top" style="padding-right:12px;">
                                                    <div style="background-color:#22C55E; border-radius:50%; width:28px; height:28px; text-align:center; line-height:28px; color:#ffffff; font-size:14px; font-weight:bold;">
                                                        &#10003;
                                                    </div>
                                                </td>
                                                <td valign="top">
                                                    <p style="color:#15803D; font-size:13px; line-height:1.6; margin:0;">
                                                        <strong>Kode berlaku selama 10 menit.</strong><br>
                                                        Jangan bagikan kode ini kepada siapapun, termasuk pihak yang mengatasnamakan Zad Apps.
                                                    </p>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                            
                            <!-- Warning -->
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color:#FEF3C7; border:1px solid #FDE68A; border-radius:12px;">
                                <tr>
                                    <td style="padding:16px 20px;">
                                        <p style="color:#92400E; font-size:13px; line-height:1.6; margin:0;">
                                            &#9888;&#65039; Jika Anda tidak meminta OTP ini, silakan abaikan email ini. 
                                            Someone might be trying to register with your email.
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    
                    <!-- Footer -->
                    <tr>
                        <td style="background-color:#123833; padding:24px 40px; text-align:center; border-top:1px solid #1A534B;">
                            <p style="color:rgba(255,255,255,0.5); font-size:11px; letter-spacing:1px; text-transform:uppercase; margin:0 0 8px; font-weight:600;">
                                &copy; 2026 Zad Apps System
                            </p>
                            <p style="color:rgba(255,255,255,0.35); font-size:11px; margin:0;">
                                Email ini dikirim secara otomatis, mohon tidak membalas email ini.
                            </p>
                        </td>
                    </tr>
                    
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
