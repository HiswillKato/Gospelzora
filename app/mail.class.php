<?php
/**
 * Mail — transactional email via local Postfix (SMTP on loopback).
 *
 * Wraps PHPMailer (vendor/autoload.php). One public method, send(), with one branch per
 * message kind: 'verify', 'reset', 'changed' and 'contact'.
 * Delivery failures are logged as `email.failed` in the activity log
 * (the caller's success outcome never depends on delivery).
 */

use PHPMailer\PHPMailer\Exception as MailerException;
use PHPMailer\PHPMailer\PHPMailer;

// PHPMailer 7 is namespaced, so nothing resolves without Composer's autoloader.
// Loaded here rather than in bootstrap.php because this is the only consumer.
require_once ROOT_PATH . '/vendor/autoload.php';

class Mail {

    private Analytics $analytics;

    public function __construct(Analytics $analytics) {
        $this->analytics = $analytics;
    }

    /**
     * A configured SMTP transport (authenticated against local Postfix).
     *
     * PHP's FILTER_VALIDATE_EMAIL rejects dotless domains, so PHPMailer would
     * refuse the local `mail@localhost` From. Install a validator that accepts
     * `@localhost` and defers everything else to the default filter.
     */
    private function transport(): PHPMailer {
        PHPMailer::$validator = function (string $address): bool {
            if (preg_match('/^[^\s@]+@localhost$/i', $address)) {
                return true;
            }
            return filter_var($address, FILTER_VALIDATE_EMAIL) !== false;
        };

        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = MAIL_HOST;
        $mail->Port = (int)MAIL_PORT;
        $mail->SMTPAuth = MAIL_USER !== '';
        if ($mail->SMTPAuth) {
            $mail->Username = MAIL_USER;
            $mail->Password = MAIL_PASS;
        }
        $mail->SMTPAutoTLS = false;  // plain loopback; port 25 has no trusted cert
        $mail->SMTPSecure = '';
        $mail->Timeout = 10;
        $mail->CharSet = PHPMailer::CHARSET_UTF8;
        $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
        return $mail;
    }

    /**
     * Send one HTML message; logs failures and returns whether it was sent. Delivery is
     * best-effort: nothing the transport throws (including a bad address or a dead SMTP server) may
     * fail the caller's action.
     */
    private function deliver(string $to, string $toName, string $subject, string $html): bool {
        try {
            $mail = $this->transport();
            $mail->addAddress($to, $toName);
            $mail->Subject = MAIL_SUBJECT_PREFIX . $subject;
            $mail->msgHTML($html);
            $mail->send();
            return true;
        } catch (Throwable $e) {
            $this->failure($to, $e instanceof MailerException ? $e->getMessage() : $e::class);
            return false;
        }
    }

    /** Record a delivery failure without ever throwing (the DB may be down too). */
    private function failure(string $to, string $reason): void {
        try {
            $this->analytics->log('email.failed', sprintf("To: %s - %s", $to, $reason));
        } catch (Throwable $e) {
            error_log('Email delivery failure could not be logged: ' . $e);
        }
    }

    /** Branded HTML shell for all transactional emails. */
    private function layout(string $title, string $bodyHtml): string {
        $site = SITE_NAME;
        return '<div style="background:#f5f2fa;padding:32px 12px">'
            . '<div style="max-width:560px;margin:0 auto;background:#ffffff;border-radius:12px;overflow:hidden;font-family:Arial,Helvetica,sans-serif;color:#2a2a3a">'
            . '<div style="background:#5b21b6;color:#ffffff;padding:20px 28px;font-size:20px;font-weight:bold">' . $site . '</div>'
            . '<div style="padding:28px">'
            . '<h2 style="margin:0 0 16px;font-size:18px">' . e($title) . '</h2>'
            . $bodyHtml
            . '</div>'
            . '<div style="padding:14px 28px;background:#faf8fc;color:#8a8794;font-size:12px">' . $site . ' &middot; ' . SITE_TAGLINE . '</div>'
            . '</div></div>';
    }

    /** CTA button + the raw link below it (for copy/paste). */
    private function button(string $url, string $label): string {
        return '<p style="margin:22px 0"><a href="' . e($url) . '"'
            . ' style="display:inline-block;background:#5b21b6;color:#ffffff;padding:12px 24px;border-radius:8px;text-decoration:none;font-weight:bold">'
            . e($label) . '</a></p>'
            . '<p style="font-size:12px;color:#8a8794">Or paste this link: ' . e($url) . '</p>';
    }

    /**
     * The single outbound path, one branch per message kind. $data carries that kind's arguments:
     *
     *   send('verify',  [$user, $token])  address verification after registration
     *   send('reset',   [$user, $token])  password-reset link
     *   send('changed', [$user])          "your password was changed" notice
     *   send('contact', [$form])          contact-form message to the site admin
     *
     * Every branch returns whether the message was handed to the transport. An unknown kind is a
     * no-op returning false.
     */
    public function send(string $kind, array $data = []): bool {
        switch ($kind) {
            case 'verify':
                [$user, $token] = $data;
            $url = url('verify-email?token=' . rawurlencode($token));
            $body = '<p>Hi ' . e($user['name']) . ',</p>'
                . '<p>Welcome to ' . SITE_NAME . '! Please confirm your email address to activate your account.</p>'
                . $this->button($url, 'Verify My Email')
                . '<p>This link expires in ' . (int)MAIL_VERIFY_EXPIRY_HOURS . ' hours.</p>'
                . '<p>If you did not create this account, you can ignore this email.</p>';
            return $this->deliver($user['email'], $user['name'], 'Verify your email address', $this->layout('Verify your email address', $body));
                break;

            case 'reset':
                [$user, $token] = $data;
            $url = url('reset?token=' . rawurlencode($token));
            $hours = (int)MAIL_RESET_EXPIRY_HOURS;
            $body = '<p>Hi ' . e($user['name']) . ',</p>'
                . '<p>We received a request to reset the password for your ' . SITE_NAME . ' account.</p>'
                . $this->button($url, 'Reset My Password')
                . '<p>This link expires in ' . $hours . ' hour' . ($hours === 1 ? '' : 's') . '.</p>'
                . '<p>If you did not request this, no changes were made — you can safely ignore this email.</p>';
            return $this->deliver($user['email'], $user['name'], 'Reset your password', $this->layout('Reset your password', $body));
                break;

            case 'changed':
                [$user] = $data;
            $body = '<p>Hi ' . e($user['name']) . ',</p>'
                . '<p>The password for your ' . SITE_NAME . ' account was just changed.</p>'
                . '<p>If you did this, you are all set. If not, contact us right away.</p>';
            return $this->deliver($user['email'], $user['name'], 'Your password was changed', $this->layout('Your password was changed', $body));
                break;

            case 'contact':
            $body = '<p><strong>Name:</strong> ' . e($data['name']) . '</p>'
                . '<p><strong>Email:</strong> ' . e($data['email']) . '</p>'
                . '<p><strong>Subject:</strong> ' . e($data['subject']) . '</p>'
                . '<p><strong>Message:</strong></p>'
                . '<p style="white-space:pre-wrap">' . e($data['message']) . '</p>';
            $subject = 'Contact: ' . mb_substr((string)$data['subject'], 0, 60);
            return $this->deliver(MAIL_ADMIN, '', $subject, $this->layout('New contact message', $body));
                break;
        }
        return false;
    }
}
