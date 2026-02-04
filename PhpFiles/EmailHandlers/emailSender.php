<?php
// PhpFiles/EmailHandlers/emailSender.php

require_once __DIR__ . '/../../composer-email-handler/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class EmailSender
{
    private PHPMailer $mail;

    private string $defaultFromEmail;
    private string $defaultFromName;
    private string $templatesRoot;

    private array $templateMap = [
        'verify'       => 'emails/verifyEmail.php',
        'one_time'     => 'emails/oneTimeAccess.php',
        'announcement' => 'emails/announcement.php',
        'transaction'  => 'emails/transactionNotification.php',
    ];

    private array $typeSenders = [];

    public function __construct(array $smtpConfig = [], ?string $templatesRoot = null)
    {
        $this->mail = new PHPMailer(true);
        $this->templatesRoot = $templatesRoot ?: (__DIR__ . '/EmailTemplates');

        // ---- SMTP config ----
        $host     = $smtpConfig['host'] ?? '';
        $username = $smtpConfig['username'] ?? '';
        $password = $smtpConfig['password'] ?? '';
        $port     = $smtpConfig['port'] ?? 465;

        // ✅ Safe default & allow string config ('ssl'/'tls')
        $secure = $smtpConfig['secure'] ?? PHPMailer::ENCRYPTION_SMTPS;
        if (is_string($secure)) {
            $s = strtolower($secure);
            $secure = ($s === 'tls') ? PHPMailer::ENCRYPTION_STARTTLS : PHPMailer::ENCRYPTION_SMTPS;
        }

        $smtpAuth = $smtpConfig['smtp_auth'] ?? true;

        $this->defaultFromEmail = $smtpConfig['from_email'] ?? '';
        $this->defaultFromName  = $smtpConfig['from_name'] ?? 'Barangay San Jose';
        $this->typeSenders      = $smtpConfig['senders'] ?? [];

        // ---- PHPMailer setup ----
        $this->mail->isSMTP();
        $this->mail->Host       = $host;
        $this->mail->SMTPAuth   = $smtpAuth;
        $this->mail->Username   = $username;
        $this->mail->Password   = $password;
        $this->mail->SMTPSecure = $secure;
        $this->mail->Port       = $port;

        $this->mail->CharSet = 'UTF-8';
        $this->mail->isHTML(true);

        if ($this->defaultFromEmail !== '') {
            $this->mail->setFrom($this->defaultFromEmail, $this->defaultFromName);
        }
    }

    public function send(array $options): bool
    {
        try {
            $this->mail->clearAllRecipients();
            $this->mail->clearAttachments();
            $this->mail->clearReplyTos();

            // Auto sender by type
            $type = $options['type'] ?? null;
            if ($type && isset($this->typeSenders[$type])) {
                $options['from_email'] = $options['from_email'] ?? ($this->typeSenders[$type]['from_email'] ?? null);
                $options['from_name']  = $options['from_name']  ?? ($this->typeSenders[$type]['from_name'] ?? null);
            }

            // From
            $fromEmail = $options['from_email'] ?? $this->defaultFromEmail;
            $fromName  = $options['from_name']  ?? $this->defaultFromName;

            if ($fromEmail !== '') {
                $this->mail->setFrom($fromEmail, $fromName);
            }

            // To
            if (empty($options['to'])) {
                throw new Exception("Missing 'to' address.");
            }
            if (is_array($options['to'])) {
                foreach ($options['to'] as $addr) $this->mail->addAddress($addr);
            } else {
                $this->mail->addAddress($options['to']);
            }

            // Subject
            $subject = trim($options['subject'] ?? '');
            if ($subject === '') throw new Exception("Missing 'subject'.");
            $this->mail->Subject = $subject;

            // Template by type
            if (empty($options['template']) && !empty($options['type'])) {
                $t = $options['type'];
                if (!isset($this->templateMap[$t])) throw new Exception("Unknown email type: {$t}");
                $options['template'] = $this->templateMap[$t];
            }

            // Body
            $bodyHtml = $options['bodyHtml'] ?? '';
            $bodyText = $options['bodyText'] ?? '';

            if (!empty($options['template'])) {
                $data = $options['data'] ?? [];
                $data['subject'] = $data['subject'] ?? $subject;

                $bodyHtml = $this->renderTemplate($options['template'], $data);
                $bodyText = $bodyText ?: $this->htmlToText($bodyHtml);
            }

            if ($bodyHtml === '') throw new Exception("Email body is empty.");

            $this->mail->Body    = $bodyHtml;
            $this->mail->AltBody = $bodyText ?: $this->htmlToText($bodyHtml);

            return $this->mail->send();

        } catch (Exception $e) {
            error_log('[EmailSender] ' . $e->getMessage() . ' | ' . $this->mail->ErrorInfo);
            return false;
        }
    }

    private function renderTemplate(string $templateRelativePath, array $data): string
    {
        $templatePath = $this->templatesRoot . '/' . ltrim($templateRelativePath, '/');
        $layoutPath   = $this->templatesRoot . '/layout.php';

        if (!file_exists($templatePath) || !file_exists($layoutPath)) {
            throw new Exception("Email template or layout missing.");
        }

        $data['__content_template'] = $templatePath;

        ob_start();
        extract($data, EXTR_SKIP);
        include $layoutPath;
        return ob_get_clean();
    }

    private function htmlToText(string $html): string
    {
        return trim(
            preg_replace(
                "/\n{3,}/",
                "\n\n",
                html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8')
            )
        );
    }
}
