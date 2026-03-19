<?php
// PhpFiles/EmailHandlers/emailSender.php

$emailAutoloadCandidates = [
    __DIR__ . '/../../composer-email-handler/vendor/autoload.php',
    __DIR__ . '/../../vendor/autoload.php',
];
foreach ($emailAutoloadCandidates as $emailAutoloadPath) {
    if (is_file($emailAutoloadPath)) {
        require_once $emailAutoloadPath;
    }
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class EmailSender
{
    private ?PHPMailer $mail = null;
    private string $lastError = '';

    private string $defaultFromEmail;
    private string $defaultFromName;
    private string $templatesRoot;

    private array $templateMap = [
        'verify'       => 'emails/verifyEmail.php',
        'one_time'     => 'emails/oneTimeAccess.php',
        'onboarding_access' => 'emails/onboardingAccess.php',
        'announcement' => 'emails/announcement.php',
        'transaction'  => 'emails/transactionNotification.php',
    ];

    private array $typeSenders = [];

    public function __construct(array $smtpConfig = [], ?string $templatesRoot = null)
    {
        $this->templatesRoot = $templatesRoot ?: (__DIR__ . '/EmailTemplates');

        $host = trim((string)($smtpConfig['host'] ?? ''));
        $username = trim((string)($smtpConfig['username'] ?? ''));
        $password = (string)($smtpConfig['password'] ?? '');
        $port = (int)($smtpConfig['port'] ?? 465);
        $smtpAuth = (bool)($smtpConfig['smtp_auth'] ?? true);

        $this->defaultFromEmail = trim((string)($smtpConfig['from_email'] ?? $username));
        $this->defaultFromName = trim((string)($smtpConfig['from_name'] ?? 'Barangay San Jose'));
        $this->typeSenders = is_array($smtpConfig['senders'] ?? null) ? $smtpConfig['senders'] : [];

        if (!class_exists(PHPMailer::class)) {
            $this->lastError = 'PHPMailer is unavailable. Ensure the mailer vendor files are deployed.';
            error_log('[EmailSender] ' . $this->lastError);
            return;
        }

        $this->mail = new PHPMailer(true);

        // Safe default & allow string config ('ssl'/'tls')
        $secure = $smtpConfig['secure'] ?? PHPMailer::ENCRYPTION_SMTPS;
        if (is_string($secure)) {
            $s = strtolower(trim($secure));
            if ($s === 'tls' || $s === 'starttls') {
                $secure = PHPMailer::ENCRYPTION_STARTTLS;
            } elseif ($s === 'ssl' || $s === 'smtps') {
                $secure = PHPMailer::ENCRYPTION_SMTPS;
            } else {
                $secure = '';
            }
        }

        // ---- PHPMailer setup ----
        $this->mail->isSMTP();
        $this->mail->Host = $host;
        $this->mail->SMTPAuth = $smtpAuth;
        $this->mail->Username = $username;
        $this->mail->Password = $password;
        $this->mail->SMTPSecure = $secure;
        $this->mail->Port = $port;
        $this->mail->Timeout = (int)($smtpConfig['timeout'] ?? 30);
        $this->mail->CharSet = 'UTF-8';
        $this->mail->isHTML(true);

        if (!empty($smtpConfig['smtp_options']) && is_array($smtpConfig['smtp_options'])) {
            $this->mail->SMTPOptions = $smtpConfig['smtp_options'];
        }

        if ($this->defaultFromEmail !== '') {
            $this->mail->setFrom($this->defaultFromEmail, $this->defaultFromName);
        }
    }

    public function send(array $options): bool
    {
        try {
            $this->lastError = '';
            if (!($this->mail instanceof PHPMailer)) {
                throw new Exception('Mailer is not initialized. Check SMTP dependencies on the server.');
            }

            if (trim((string)$this->mail->Host) === '') {
                throw new Exception('SMTP host is not configured.');
            }
            if ($this->mail->SMTPAuth && (trim((string)$this->mail->Username) === '' || $this->mail->Password === '')) {
                throw new Exception('SMTP username or password is missing.');
            }

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

            if ($fromEmail === '') {
                throw new Exception('From email is not configured.');
            }
            $this->mail->setFrom($fromEmail, $fromName);

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

            $sent = $this->mail->send();
            if (!$sent) {
                $this->lastError = $this->mail->ErrorInfo;
                error_log('[EmailSender] ' . $this->lastError);
            }
            return $sent;

        } catch (Exception $e) {
            $mailError = $this->mail instanceof PHPMailer ? (string)$this->mail->ErrorInfo : '';
            $this->lastError = trim($e->getMessage() . ($mailError !== '' ? ' | ' . $mailError : ''));
            error_log('[EmailSender] ' . $this->lastError);
            return false;
        }
    }

    public function getLastError(): string
    {
        return $this->lastError;
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
