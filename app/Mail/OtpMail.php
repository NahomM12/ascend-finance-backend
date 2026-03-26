<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class OtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $otp;
    public int $expiresInMinutes;

    public function __construct(string $otp, int $expiresInMinutes)
    {
        $this->otp = $otp;
        $this->expiresInMinutes = $expiresInMinutes;
    }

    public function build()
    {
        $subject = 'Your Ascend verification code';

        $html = '
            <html>
                <body style="font-family: system-ui, -apple-system, BlinkMacSystemFont, \"Segoe UI\", sans-serif; background-color: #0f172a; padding: 32px;">
                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width: 480px; margin: 0 auto; background-color: #020617; border-radius: 16px; border: 1px solid #1e293b;">
                        <tr>
                            <td style="padding: 32px; text-align: center;">
                                <div style="display: inline-flex; align-items: center; justify-content: center; width: 40px; height: 40px; border-radius: 12px; background: linear-gradient(135deg, #22c55e, #0ea5e9); margin-bottom: 16px;"></div>
                                <h1 style="margin: 0 0 8px; font-size: 24px; line-height: 1.2; color: #e5e7eb;">Verify your email</h1>
                                <p style="margin: 0 0 24px; font-size: 14px; color: #9ca3af;">Use the verification code below to complete your sign in.</p>
                                <div style="display: inline-flex; align-items: center; justify-content: center; padding: 12px 24px; border-radius: 999px; background-color: #020617; border: 1px solid #4b5563; margin-bottom: 16px;">
                                    <span style="font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, \"Liberation Mono\", \"Courier New\", monospace; font-size: 24px; letter-spacing: 0.24em; color: #f9fafb;">' . e($this->otp) . '</span>
                                </div>
                                <p style="margin: 0 0 16px; font-size: 12px; color: #9ca3af;">This code will expire in ' . $this->expiresInMinutes . ' minutes.</p>
                                <p style="margin: 0; font-size: 11px; color: #6b7280;">If you did not request this code, you can safely ignore this email.</p>
                            </td>
                        </tr>
                    </table>
                </body>
            </html>
        ';

        return $this->subject($subject)->html($html);
    }
}

