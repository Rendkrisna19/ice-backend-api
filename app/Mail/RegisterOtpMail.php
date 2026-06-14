<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class RegisterOtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $otp;
    public string $logoUrl;

    public function __construct(string $otp)
    {
        $this->otp = $otp;
        // Logo URL - bisa diakses dari email client
        $this->logoUrl = asset('logo.png');
    }

    public function build(): self
    {
        return $this->subject('Kode OTP Pendaftaran - Zad Apps')
            ->view('emails.register-otp');
    }
}
