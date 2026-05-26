<?php
// nuBuilder Next - Email Notification System
// SMTP + PHPMailer ready, with template support

class NuEmailer {
    private $db;
    private $fromEmail;
    private $fromName;
    private $smtpHost;
    private $smtpPort;
    private $smtpUser;
    private $smtpPass;
    private $smtpSecure;

    public function __construct() {
        $this->db = NuDatabase::getInstance();
        $this->fromEmail = $GLOBALS['nuConfig']['emailFrom'] ?? 'noreply@nubuilder.local';
        $this->fromName = $GLOBALS['nuConfig']['emailFromName'] ?? 'nuBuilder Next';
        $this->smtpHost = $GLOBALS['nuConfig']['smtpHost'] ?? '';
        $this->smtpPort = $GLOBALS['nuConfig']['smtpPort'] ?? 587;
        $this->smtpUser = $GLOBALS['nuConfig']['smtpUser'] ?? '';
        $this->smtpPass = $GLOBALS['nuConfig']['smtpPass'] ?? '';
        $this->smtpSecure = $GLOBALS['nuConfig']['smtpSecure'] ?? 'tls';
    }

    public function send($to, $subject, $body, $attachments = []) {
        if ($this->smtpHost && class_exists('PHPMailer\PHPMailer\PHPMailer')) {
            return $this->sendSMTP($to, $subject, $body, $attachments);
        }
        return $this->sendMail($to, $subject, $body);
    }

    private function sendSMTP($to, $subject, $body, $attachments) {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = $this->smtpHost;
            $mail->SMTPAuth = true;
            $mail->Username = $this->smtpUser;
            $mail->Password = $this->smtpPass;
            $mail->SMTPSecure = $this->smtpSecure;
            $mail->Port = $this->smtpPort;
            $mail->setFrom($this->fromEmail, $this->fromName);
            $mail->addAddress($to);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $body;
            foreach ($attachments as $att) {
                $mail->addAttachment($att);
            }
            return $mail->send();
        } catch (Exception $e) {
            error_log('SMTP Error: ' . $e->getMessage());
            return false;
        }
    }

    private function sendMail($to, $subject, $body) {
        $headers = "From: {$this->fromName} <{$this->fromEmail}>
";
        $headers .= "Reply-To: {$this->fromEmail}
";
        $headers .= "MIME-Version: 1.0
";
        $headers .= "Content-Type: text/html; charset=UTF-8
";
        return mail($to, $subject, $body, $headers);
    }

    public function notifyWorkflow($instanceId, $action, $comment = '') {
        $instance = $this->db->fetchOne("SELECT wfi.*, wf.wf_name, d.doc_title, u.usr_email, u.usr_username 
            FROM nu_workflow_instances wfi 
            JOIN nu_workflows wf ON wfi.wfi_wf_id = wf.wf_id 
            LEFT JOIN nu_documents d ON wfi.wfi_document_id = d.doc_id 
            LEFT JOIN nu_users u ON wfi.wfi_started_by = u.usr_id 
            WHERE wfi.wfi_id = :id", [':id' => $instanceId]);

        if (!$instance || !$instance['usr_email']) return false;

        $subject = "Workflow {$action}: {$instance['wf_name']}";
        $body = "<h2>Workflow Notification</h2>";
        $body .= "<p><strong>Workflow:</strong> {$instance['wf_name']}</p>";
        $body .= "<p><strong>Document:</strong> " . ($instance['doc_title'] ?? 'N/A') . "</p>";
        $body .= "<p><strong>Status:</strong> " . ucfirst($action) . "</p>";
        if ($comment) $body .= "<p><strong>Comment:</strong> " . htmlspecialchars($comment) . "</p>";
        $body .= "<p><a href='{$GLOBALS['nuConfig']['appUrl']}index.php?module=workflow'>View in nuBuilder</a></p>";

        return $this->send($instance['usr_email'], $subject, $body);
    }

    public function notifyUser($userId, $subject, $template, $data = []) {
        $user = $this->db->fetchOne("SELECT * FROM nu_users WHERE usr_id = :id", [':id' => $userId]);
        if (!$user || !$user['usr_email']) return false;

        $body = $this->renderTemplate($template, array_merge($data, ['username' => $user['usr_username']]));
        return $this->send($user['usr_email'], $subject, $body);
    }

    private function renderTemplate($template, $data) {
        $templates = [
            'welcome' => '<h2>Welcome {{username}}</h2><p>Your account has been created.</p>',
            'password_reset' => '<h2>Password Reset</h2><p>Click <a href="{{link}}">here</a> to reset.</p>',
            'workflow_pending' => '<h2>Approval Required</h2><p>A workflow is waiting for your action.</p>',
            'default' => '<h2>Notification</h2><p>{{message}}</p>'
        ];
        $html = $templates[$template] ?? $templates['default'];
        foreach ($data as $key => $val) {
            $html = str_replace('{{' . $key . '}}', htmlspecialchars($val), $html);
        }
        return $html;
    }
}
?>
