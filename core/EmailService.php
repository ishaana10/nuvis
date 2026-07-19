<?php
/**
 * EmailService - Core email sending service for nub5-dev
 * Supports PHP mail(), SMTP (via PHPMailer-compatible native SMTP), and sendmail.
 * Can be reused from forms, workflows, and other modules.
 */
class EmailService {

    private $config;

    public function __construct(array $config = []) {
        // Merge with global config if available
        $defaults = [
            'driver'        => 'mail',   // 'mail' | 'smtp' | 'sendmail'
            'smtp_host'     => '',
            'smtp_port'     => 587,
            'smtp_secure'   => 'tls',    // 'tls' | 'ssl' | ''
            'smtp_auth'     => true,
            'smtp_username' => '',
            'smtp_password' => '',
            'from_email'    => 'noreply@example.com',
            'from_name'     => 'nub5-dev',
            'reply_to'      => '',
        ];

        // First attempt to load dynamic SMTP settings from the database
        try {
            $db = NuDatabase::getInstance();
            $rows = $db->fetchAll("SELECT setting_key, setting_value FROM nu_email_settings");
            foreach ($rows as $row) {
                $k = $row['setting_key'];
                $v = $row['setting_value'];
                if ($k === 'smtp_auth') {
                    $defaults[$k] = ($v == '1');
                } else if ($v !== '') {
                    $defaults[$k] = $v;
                }
            }
        } catch (\Throwable $t) {
            // DB table might not be seeded or created yet
        }

        // Pull from global config.php constants if defined (take priority over DB)
        if (defined('EMAIL_DRIVER'))        $defaults['driver']        = EMAIL_DRIVER;
        if (defined('EMAIL_SMTP_HOST'))     $defaults['smtp_host']     = EMAIL_SMTP_HOST;
        if (defined('EMAIL_SMTP_PORT'))     $defaults['smtp_port']     = EMAIL_SMTP_PORT;
        if (defined('EMAIL_SMTP_SECURE'))   $defaults['smtp_secure']   = EMAIL_SMTP_SECURE;
        if (defined('EMAIL_SMTP_USERNAME')) $defaults['smtp_username'] = EMAIL_SMTP_USERNAME;
        if (defined('EMAIL_SMTP_PASSWORD')) $defaults['smtp_password'] = EMAIL_SMTP_PASSWORD;
        if (defined('EMAIL_FROM'))          $defaults['from_email']    = EMAIL_FROM;
        if (defined('EMAIL_FROM_NAME'))     $defaults['from_name']     = EMAIL_FROM_NAME;

        $this->config = array_merge($defaults, $config);
    }

    /**
     * Send an email.
     *
     * @param string|array $to      Recipient email or ['email' => 'name'] map
     * @param string       $subject Subject line
     * @param string       $body    HTML body
     * @param array        $options ['cc', 'bcc', 'attachments', 'reply_to', 'text_body']
     * @return array ['success' => bool, 'message' => string]
     */
    public function send($to, string $subject, string $body, array $options = []): array {
        try {
            switch ($this->config['driver']) {
                case 'smtp':
                    return $this->sendSmtp($to, $subject, $body, $options);
                case 'sendmail':
                    return $this->sendMail($to, $subject, $body, $options, true);
                default:
                    return $this->sendMail($to, $subject, $body, $options, false);
            }
        } catch (\Throwable $e) {
            $this->logEmail('FAIL', $to, $subject, $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // -------------------------------------------------------------------------
    // PHP mail() driver
    // -------------------------------------------------------------------------
    private function sendMail($to, string $subject, string $body, array $options, bool $useSendmail): array {
        $toStr    = $this->formatRecipients($to);
        $headers  = $this->buildHeaders($options);
        $textBody = $options['text_body'] ?? strip_tags($body);

        // Multipart MIME
        $boundary = md5(uniqid((string)time()));
        $headers .= "Content-Type: multipart/alternative; boundary=\"$boundary\"\r\n";

        $fullBody  = "--$boundary\r\n";
        $fullBody .= "Content-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n";
        $fullBody .= chunk_split(base64_encode($textBody)) . "\r\n";
        $fullBody .= "--$boundary\r\n";
        $fullBody .= "Content-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n";
        $fullBody .= chunk_split(base64_encode($body)) . "\r\n";
        $fullBody .= "--{$boundary}--";

        $extraParams = $useSendmail ? '-f' . $this->config['from_email'] : '';
        $result = @mail($toStr, $subject, $fullBody, $headers, $extraParams);

        $this->logEmail($result ? 'SENT' : 'FAIL', $to, $subject, $result ? '' : error_get_last()['message'] ?? 'mail() returned false');
        return ['success' => (bool)$result, 'message' => $result ? 'Email sent successfully.' : 'mail() failed.'];
    }

    // -------------------------------------------------------------------------
    // Native SMTP driver (socket-based, no PHPMailer required)
    // -------------------------------------------------------------------------
    private function sendSmtp($to, string $subject, string $body, array $options): array {
        $host    = $this->config['smtp_host'];
        $port    = (int)$this->config['smtp_port'];
        $secure  = strtolower((string)$this->config['smtp_secure']);
        $timeout = 15;

        $address = ($secure === 'ssl') ? "ssl://{$host}" : $host;
        $sock = @fsockopen($address, $port, $errno, $errstr, $timeout);
        if (!$sock) throw new \RuntimeException("SMTP connect failed ({$errno}): {$errstr}");

        $recv = fgets($sock, 512);
        if (substr($recv, 0, 3) !== '220') throw new \RuntimeException("SMTP greeting failed: {$recv}");

        $this->smtpSend($sock, "EHLO " . gethostname());

        if ($secure === 'tls') {
            $this->smtpSend($sock, 'STARTTLS');
            stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            $this->smtpSend($sock, "EHLO " . gethostname());
        }

        if ($this->config['smtp_auth']) {
            $this->smtpSend($sock, 'AUTH LOGIN');
            $this->smtpSend($sock, base64_encode((string)$this->config['smtp_username']));
            $this->smtpSend($sock, base64_encode((string)$this->config['smtp_password']));
        }

        $fromEmail = $this->config['from_email'];
        $this->smtpSend($sock, "MAIL FROM:<{$fromEmail}>");

        $recipients = $this->normalizeRecipients($to);
        foreach ($recipients as $email => $name) {
            $this->smtpSend($sock, "RCPT TO:<{$email}>");
        }
        foreach (($options['cc'] ?? []) as $cc)  { $this->smtpSend($sock, "RCPT TO:<{$cc}>"); }
        foreach (($options['bcc'] ?? []) as $bcc) { $this->smtpSend($sock, "RCPT TO:<{$bcc}>"); }

        $this->smtpSend($sock, 'DATA', '354');

        $boundary = md5(uniqid((string)time()));
        $textBody = $options['text_body'] ?? strip_tags($body);
        $toStr    = $this->formatRecipients($to);
        $fromStr  = $this->config['from_name'] ? "{$this->config['from_name']} <{$fromEmail}>" : $fromEmail;
        $replyTo  = $options['reply_to'] ?? $this->config['reply_to'];

        $msg  = "From: {$fromStr}\r\n";
        $msg .= "To: {$toStr}\r\n";
        if (!empty($options['cc']))  $msg .= "Cc: " . implode(', ', $options['cc']) . "\r\n";
        if ($replyTo)                $msg .= "Reply-To: {$replyTo}\r\n";
        $msg .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
        $msg .= "MIME-Version: 1.0\r\n";
        $msg .= "Content-Type: multipart/alternative; boundary=\"$boundary\"\r\n";
        $msg .= "Date: " . date('r') . "\r\n\r\n";
        $msg .= "--$boundary\r\n";
        $msg .= "Content-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n";
        $msg .= chunk_split(base64_encode($textBody)) . "\r\n";
        $msg .= "--$boundary\r\n";
        $msg .= "Content-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n";
        $msg .= chunk_split(base64_encode($body)) . "\r\n";
        $msg .= "--{$boundary}--\r\n";
        $msg .= ".";

        $this->smtpSend($sock, $msg);
        $this->smtpSend($sock, 'QUIT', '221');
        fclose($sock);

        $this->logEmail('SENT', $to, $subject, '');
        return ['success' => true, 'message' => 'Email sent via SMTP.'];
    }

    private function smtpSend($sock, string $cmd, string $expectCode = null): string {
        fwrite($sock, $cmd . "\r\n");
        $response = fgets($sock, 512);
        $code = substr($response, 0, 3);
        $expected = $expectCode ?? ($cmd === 'DATA' ? '354' : '2');
        if (strlen($expected) === 1 && $code[0] !== $expected) {
            throw new \RuntimeException("SMTP error for [{$cmd}]: {$response}");
        } elseif (strlen($expected) === 3 && $code !== $expected) {
            throw new \RuntimeException("SMTP error for [{$cmd}]: {$response}");
        }
        return $response;
    }

    // -------------------------------------------------------------------------
    // Template rendering
    // -------------------------------------------------------------------------
    /**
     * Render an email template from the DB by slug, replacing {{placeholders}}.
     *
     * @param string $slug       Template slug (e.g. 'form_submission')
     * @param array  $variables  Key-value pairs to replace in subject and body
     * @return array ['subject' => string, 'body' => string] or null if not found
     */
    public static function renderTemplate(string $slug, array $variables = [], $db = null): ?array {
        try {
            $db = NuDatabase::getInstance();
            $tpl = $db->fetchOne("SELECT subject, body FROM nu_email_templates WHERE slug = ? AND is_active = 1 LIMIT 1", [$slug]);
            if (!$tpl) return null;

            foreach ($variables as $key => $value) {
                $tpl['subject'] = str_replace('{{' . $key . '}}', (string)$value, $tpl['subject']);
                $tpl['body']    = str_replace('{{' . $key . '}}', (string)$value, $tpl['body']);
            }
            return $tpl;
        } catch (\Throwable $t) {
            return null;
        }
    }

    // -------------------------------------------------------------------------
    // Logging
    // -------------------------------------------------------------------------
    private function logEmail(string $status, $to, string $subject, string $error = ''): void {
        try {
            $db = NuDatabase::getInstance();
            $toStr   = is_array($to) ? implode(',', array_keys($to)) : $to;

            $db->insert('nu_email_log', [
                'recipient'     => $toStr,
                'subject'       => $subject,
                'status'        => $status,
                'error_message' => substr($error, 0, 1000),
                'sent_at'       => date('Y-m-d H:i:s')
            ]);
        } catch (\Throwable $t) {}
    }

    private function normalizeRecipients($to): array {
        $recipients = [];
        if (is_string($to)) {
            $parts = explode(',', $to);
            foreach ($parts as $part) {
                $part = trim($part);
                if (empty($part)) continue;
                if (preg_match('/^(.*?)\s*<(.*?)>$/', $part, $matches)) {
                    $email = trim($matches[2]);
                    $name  = trim($matches[1], ' "\'');
                    $recipients[$email] = $name;
                } else {
                    $recipients[$part] = '';
                }
            }
        } elseif (is_array($to)) {
            foreach ($to as $key => $val) {
                if (is_int($key)) {
                    $val = trim($val);
                    if (preg_match('/^(.*?)\s*<(.*?)>$/', $val, $matches)) {
                        $recipients[trim($matches[2])] = trim($matches[1], ' "\'');
                    } else {
                        $recipients[$val] = '';
                    }
                } else {
                    $recipients[trim($key)] = trim($val);
                }
            }
        }
        return $recipients;
    }

    private function formatRecipients($to): string {
        $normalized = $this->normalizeRecipients($to);
        $formatted = [];
        foreach ($normalized as $email => $name) {
            if ($name !== '') {
                $formatted[] = "\"$name\" <$email>";
            } else {
                $formatted[] = $email;
            }
        }
        return implode(', ', $formatted);
    }

    private function buildHeaders(array $options): string {
        $fromEmail = $this->config['from_email'];
        $fromName  = $this->config['from_name'];
        $fromStr   = $fromName ? "{$fromName} <{$fromEmail}>" : $fromEmail;
        $replyTo   = $options['reply_to'] ?? $this->config['reply_to'];

        $headers  = "From: {$fromStr}\r\n";
        if ($replyTo) {
            $headers .= "Reply-To: {$replyTo}\r\n";
        }
        if (!empty($options['cc'])) {
            $cc = is_array($options['cc']) ? implode(', ', $options['cc']) : $options['cc'];
            $headers .= "Cc: {$cc}\r\n";
        }
        if (!empty($options['bcc'])) {
            $bcc = is_array($options['bcc']) ? implode(', ', $options['bcc']) : $options['bcc'];
            $headers .= "Bcc: {$bcc}\r\n";
        }
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "X-Mailer: nub5-dev\r\n";
        return $headers;
    }
}
