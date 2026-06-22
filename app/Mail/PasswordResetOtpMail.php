<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PasswordResetOtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $otp;
    public string $logoUrl;

    public function __construct(string $otp)
    {
        $this->otp = $otp;
        $this->logoUrl = asset('logo.png');
    }

    public function build(): self
    {
        return $this->subject('Kode OTP Reset Password - Zad Apps')
            ->view('emails.password-reset-otp');
    }
}
