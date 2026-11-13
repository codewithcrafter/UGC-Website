<?php
/**
 * MY UGC VIDEO — Email Notification & SMTP Dispatch Engine
 * 
 * Strict Specification:
 * - Sends visitor confirmation email ONLY to the visitor who submitted the form.
 * - Zero notifications, CC, or BCC to admin or business accounts.
 * - Saves lead state prior to email attempt; email failure NEVER loses lead.
 * - Provides admin retry functionality with audit tracking.
 * - Native secure SMTP client (TLS/SSL) with fallback handling.
 */

if (!defined('CMS_ROOT')) {
    define('CMS_ROOT', dirname(__DIR__));
}
require_once CMS_ROOT . '/config/config.php';

class MailService {
    /**
     * Gets SMTP configuration from environment or constants.
     */
    public static function getConfig(): array {
        return [
            'host'       => getenv('SMTP_HOST') ?: (defined('SMTP_HOST') ? SMTP_HOST : ''),
            'port'       => (int)(getenv('SMTP_PORT') ?: (defined('SMTP_PORT') ? SMTP_PORT : 587)),
            'user'       => getenv('SMTP_USER') ?: getenv('SMTP_USERNAME') ?: (defined('SMTP_USER') ? SMTP_USER : ''),
            'pass'       => getenv('SMTP_PASS') ?: getenv('SMTP_PASSWORD') ?: (defined('SMTP_PASS') ? SMTP_PASS : ''),
            'secure'     => strtolower(getenv('SMTP_SECURE') ?: getenv('SMTP_ENCRYPTION') ?: (defined('SMTP_SECURE') ? SMTP_SECURE : 'tls')),
            'from_email' => getenv('MAIL_FROM_ADDRESS') ?: (defined('MAIL_FROM_ADDRESS') ? MAIL_FROM_ADDRESS : 'hello@myugcvideo.in'),
            'from_name'  => getenv('MAIL_FROM_NAME') ?: (defined('MAIL_FROM_NAME') ? MAIL_FROM_NAME : 'MY UGC VIDEO')
        ];
    }

    /**
     * Sends the required confirmation email to the visitor.
     * 
     * @param array $lead Associative array of lead data (id, name, email)
     * @return array ['success' => bool, 'error' => ?string]
     */
    public static function sendVisitorConfirmation(array $lead): array {
        $leadId = (int)($lead['id'] ?? 0);
        $name = trim($lead['name'] ?? 'There');
        $email = trim($lead['email'] ?? '');

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $err = "Invalid visitor email address: {$email}";
            if ($leadId > 0) {
                self::recordDeliveryStatus($leadId, 'failed', $err);
            }
            return ['success' => false, 'error' => $err];
        }

        $subject = "We've Received Your Enquiry | MY UGC VIDEO";
        $textBody = self::buildPlainTextBody($name);
        $htmlBody = self::buildHtmlBody($name);

        $config = self::getConfig();
        $sent = false;
        $errorMessage = null;

        // 1. Try Native SMTP if host is provided
        if (!empty($config['host'])) {
            $smtpResult = self::sendViaSmtp($config, $email, $name, $subject, $textBody, $htmlBody);
            if ($smtpResult['success']) {
                $sent = true;
            } else {
                $errorMessage = "SMTP delivery error: " . $smtpResult['error'];
            }
        } else {
            // 2. Fallback to PHP native mail() if on a server with sendmail
            $mailResult = self::sendViaNativeMail($config, $email, $name, $subject, $textBody, $htmlBody);
            if ($mailResult['success']) {
                $sent = true;
            } else {
                $errorMessage = "SMTP unconfigured; native mail() unconfirmed: " . ($mailResult['error'] ?? 'Mail transfer agent not available');
            }
        }

        $status = $sent ? 'sent' : 'failed';
        if ($leadId > 0) {
            self::recordDeliveryStatus($leadId, $status, $errorMessage);
        }

        return [
            'success' => $sent,
            'status'  => $status,
            'error'   => $errorMessage
        ];
    }

    /**
     * Admin-only retry function.
     */
    public static function retryConfirmation(int $leadId): array {
        $db = getDb();
        $stmt = $db->prepare("SELECT id, name, email, email_delivery_status FROM leads WHERE id = :id");
        $stmt->execute([':id' => $leadId]);
        $lead = $stmt->fetch();

        if (!$lead) {
            return ['success' => false, 'error' => "Lead #{$leadId} not found."];
        }

        return self::sendVisitorConfirmation($lead);
    }

    /**
     * Builds strict plain text body per specification.
     */
    public static function buildPlainTextBody(string $name): string {
        return "Hi {$name},\n\n"
             . "Thank you for reaching out to MY UGC VIDEO!\n\n"
             . "We've successfully received your enquiry. Our team appreciates your interest in working with us.\n\n"
             . "Our team will review your requirements and get back to you as soon as possible.\n\n"
             . "We look forward to discussing your project!\n\n"
             . "Best regards,\n"
             . "Team MY UGC VIDEO\n"
             . "https://myugcvideo.in\n";
    }

    /**
     * Builds responsive HTML email body maintaining brand styling.
     */
    public static function buildHtmlBody(string $name): string {
        $safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
        return <<<HTML
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>We've Received Your Enquiry | MY UGC VIDEO</title>
</head>
<body style="margin: 0; padding: 30px 15px; background-color: #0b0c10; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color: #e5e7eb;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width: 600px; margin: 0 auto; background-color: #12141a; border-radius: 12px; border: 1px solid rgba(255,255,255,0.08); overflow: hidden;">
    <tr>
      <td style="padding: 32px 36px; background-color: #0f1117; border-bottom: 1px solid rgba(255,255,255,0.06);">
        <span style="font-size: 20px; font-weight: 800; letter-spacing: -0.5px; color: #ffffff;">MY UGC VIDEO</span>
        <span style="display: block; font-size: 11px; text-transform: uppercase; letter-spacing: 2px; color: #00f2fe; margin-top: 4px;">Creator Studio</span>
      </td>
    </tr>
    <tr>
      <td style="padding: 36px 36px 28px 36px; font-size: 15px; line-height: 1.7; color: #d1d5db;">
        <p style="margin: 0 0 18px 0; font-size: 17px; font-weight: 600; color: #ffffff;">Hi {$safeName},</p>
        <p style="margin: 0 0 18px 0;">Thank you for reaching out to <strong>MY UGC VIDEO</strong>!</p>
        <p style="margin: 0 0 18px 0;">We've successfully received your enquiry. Our team appreciates your interest in working with us.</p>
        <p style="margin: 0 0 18px 0;">Our team will review your requirements and get back to you as soon as possible.</p>
        <p style="margin: 0 0 28px 0;">We look forward to discussing your project!</p>
        <div style="padding-top: 20px; border-top: 1px solid rgba(255,255,255,0.08);">
          <p style="margin: 0; color: #9ca3af; font-size: 14px;">Best regards,<br><strong style="color: #ffffff;">Team MY UGC VIDEO</strong><br><a href="https://myugcvideo.in" style="color: #00f2fe; text-decoration: none;">https://myugcvideo.in</a></p>
        </div>
      </td>
    </tr>
  </table>
</body>
</html>
HTML;
    }

    /**
     * Updates MySQL leads table with delivery status.
     */
    private static function recordDeliveryStatus(int $leadId, string $status, ?string $error): void {
        try {
            $db = getDb();
            $stmt = $db->prepare("
                UPDATE leads 
                SET email_delivery_status = :status,
                    email_attempts = email_attempts + 1,
                    last_email_attempt_at = NOW(),
                    email_error_message = :err
                WHERE id = :id
            ");
            $stmt->execute([
                ':status' => $status,
                ':err'    => $error ? substr($error, 0, 1000) : null,
                ':id'     => $leadId
            ]);
        } catch (Throwable $e) {
            error_log("Failed to record lead #{$leadId} email status: " . $e->getMessage());
        }
    }

    /**
     * High-performance socket-based SMTP client with STARTTLS & SSL support.
     */
    private static function sendViaSmtp(array $cfg, string $toEmail, string $toName, string $subject, string $textBody, string $htmlBody): array {
        $host = $cfg['host'];
        $port = $cfg['port'];
        $timeout = 10;
        $secure = $cfg['secure']; // 'tls', 'ssl', 'none'

        $remoteHost = ($secure === 'ssl') ? "ssl://{$host}" : "tcp://{$host}";
        $socket = @fsockopen($remoteHost, $port, $errno, $errstr, $timeout);

        if (!$socket) {
            return ['success' => false, 'error' => "Could not connect to SMTP host {$host}:{$port} ({$errno} - {$errstr})"];
        }

        stream_set_timeout($socket, $timeout);

        $getReply = function() use ($socket) {
            $reply = '';
            while ($line = fgets($socket, 515)) {
                $reply .= $line;
                if (substr($line, 3, 1) === ' ') break;
            }
            return $reply;
        };

        $sendCommand = function($cmd, $expectedCode) use ($socket, $getReply) {
            fputs($socket, $cmd . "\r\n");
            $reply = $getReply();
            $code = (int)substr($reply, 0, 3);
            if ($code !== $expectedCode) {
                return ['success' => false, 'reply' => $reply];
            }
            return ['success' => true, 'reply' => $reply];
        };

        // Greeting
        $greet = $getReply();
        if ((int)substr($greet, 0, 3) !== 220) {
            fclose($socket);
            return ['success' => false, 'error' => "Invalid SMTP greeting: {$greet}"];
        }

        // EHLO
        $clientHost = gethostname() ?: 'localhost';
        $r = $sendCommand("EHLO {$clientHost}", 250);
        if (!$r['success']) {
            fclose($socket);
            return ['success' => false, 'error' => "EHLO failed: {$r['reply']}"];
        }

        // STARTTLS
        if ($secure === 'tls' && $port !== 465) {
            $r = $sendCommand("STARTTLS", 220);
            if ($r['success']) {
                $crypto = stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT);
                if (!$crypto) {
                    fclose($socket);
                    return ['success' => false, 'error' => "TLS encryption negotiation failed."];
                }
                // Resend EHLO after encryption
                $sendCommand("EHLO {$clientHost}", 250);
            }
        }

        // AUTH LOGIN if credentials given
        if (!empty($cfg['user']) && !empty($cfg['pass'])) {
            $r = $sendCommand("AUTH LOGIN", 334);
            if (!$r['success']) {
                fclose($socket);
                return ['success' => false, 'error' => "AUTH LOGIN rejected: {$r['reply']}"];
            }

            $r = $sendCommand(base64_encode($cfg['user']), 334);
            if (!$r['success']) {
                fclose($socket);
                return ['success' => false, 'error' => "SMTP username rejected: {$r['reply']}"];
            }

            $r = $sendCommand(base64_encode($cfg['pass']), 235);
            if (!$r['success']) {
                fclose($socket);
                return ['success' => false, 'error' => "SMTP authentication failed: {$r['reply']}"];
            }
        }

        // MAIL FROM
        $fromEmail = $cfg['from_email'];
        $fromName = $cfg['from_name'];
        $r = $sendCommand("MAIL FROM:<{$fromEmail}>", 250);
        if (!$r['success']) {
            fclose($socket);
            return ['success' => false, 'error' => "MAIL FROM rejected: {$r['reply']}"];
        }

        // RCPT TO (Visitor only!)
        $r = $sendCommand("RCPT TO:<{$toEmail}>", 250);
        if (!$r['success']) {
            fclose($socket);
            return ['success' => false, 'error' => "RCPT TO rejected: {$r['reply']}"];
        }

        // DATA
        $r = $sendCommand("DATA", 354);
        if (!$r['success']) {
            fclose($socket);
            return ['success' => false, 'error' => "DATA rejected: {$r['reply']}"];
        }

        // Build MIME Multipart message
        $boundary = "bnd_" . md5(uniqid(microtime(), true));
        $date = date('r');

        $headers = [];
        $headers[] = "Date: {$date}";
        $headers[] = "From: =?UTF-8?B?" . base64_encode($fromName) . "?= <{$fromEmail}>";
        $headers[] = "To: =?UTF-8?B?" . base64_encode($toName) . "?= <{$toEmail}>";
        $headers[] = "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=";
        $headers[] = "MIME-Version: 1.0";
        $headers[] = "Content-Type: multipart/alternative; boundary=\"{$boundary}\"";
        $headers[] = "X-Mailer: MY UGC VIDEO Mailer 2.0";

        $msgBody = implode("\r\n", $headers) . "\r\n\r\n";

        // Plain Text part
        $msgBody .= "--{$boundary}\r\n";
        $msgBody .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $msgBody .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $msgBody .= chunk_split(base64_encode($textBody)) . "\r\n";

        // HTML part
        $msgBody .= "--{$boundary}\r\n";
        $msgBody .= "Content-Type: text/html; charset=UTF-8\r\n";
        $msgBody .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $msgBody .= chunk_split(base64_encode($htmlBody)) . "\r\n";

        $msgBody .= "--{$boundary}--\r\n";
        $msgBody .= ".\r\n";

        fputs($socket, $msgBody);
        $finalReply = $getReply();
        $code = (int)substr($finalReply, 0, 3);

        $sendCommand("QUIT", 221);
        fclose($socket);

        if ($code === 250) {
            return ['success' => true];
        }

        return ['success' => false, 'error' => "Message rejected by SMTP server: {$finalReply}"];
    }

    /**
     * Fallback to native PHP mail()
     */
    private static function sendViaNativeMail(array $cfg, string $toEmail, string $toName, string $subject, string $textBody, string $htmlBody): array {
        $fromEmail = $cfg['from_email'];
        $fromName = $cfg['from_name'];

        $boundary = "bnd_" . md5(uniqid(microtime(), true));
        $headers = [];
        $headers[] = "From: =?UTF-8?B?" . base64_encode($fromName) . "?= <{$fromEmail}>";
        $headers[] = "Reply-To: {$fromEmail}";
        $headers[] = "MIME-Version: 1.0";
        $headers[] = "Content-Type: multipart/alternative; boundary=\"{$boundary}\"";
        $headers[] = "X-Mailer: PHP/" . phpversion();

        $msgBody = "--{$boundary}\r\n";
        $msgBody .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $msgBody .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $msgBody .= chunk_split(base64_encode($textBody)) . "\r\n";

        $msgBody .= "--{$boundary}\r\n";
        $msgBody .= "Content-Type: text/html; charset=UTF-8\r\n";
        $msgBody .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $msgBody .= chunk_split(base64_encode($htmlBody)) . "\r\n";
        $msgBody .= "--{$boundary}--\r\n";

        $encodedSubject = "=?UTF-8?B?" . base64_encode($subject) . "?=";
        $to = "=?UTF-8?B?" . base64_encode($toName) . "?= <{$toEmail}>";

        // In local XAMPP without sendmail, mail() may return false
        $ok = @mail($to, $encodedSubject, $msgBody, implode("\r\n", $headers));
        if ($ok) {
            return ['success' => true];
        }
        return ['success' => false, 'error' => 'Native mail() returned false (No local MTA/sendmail configured)'];
    }
}

