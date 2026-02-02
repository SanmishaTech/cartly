<?php

namespace App\Services;

use PHPMailer\PHPMailer\Exception as MailException;
use PHPMailer\PHPMailer\PHPMailer;

class MailService
{
    private array $config;

    public function __construct(array $config = [])
    {
        $this->config = $config ?: $this->loadConfig();
    }

    public function send(
        string $toEmail,
        string $toName,
        string $subject,
        string $htmlBody,
        ?string $textBody = null
    ): bool {
        $mailer = new PHPMailer(true);

        try {
            $mailer->XMailer = ' ';
            $mailer->isSMTP();
            $mailer->Host = $this->config['host'];
            $mailer->Port = (int) $this->config['port'];
            $mailer->SMTPAuth = $this->config['username'] !== '';
            $mailer->Username = $this->config['username'];
            $mailer->Password = $this->config['password'];
            $mailer->CharSet = 'UTF-8';

            $encryption = strtolower($this->config['encryption']);
            if ($encryption === 'tls') {
                $mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            } elseif ($encryption === 'ssl') {
                $mailer->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            }

            $mailer->setFrom(
                $this->config['from_address'],
                $this->config['from_name']
            );
            $mailer->addAddress($toEmail, $toName);
            $mailer->Subject = $subject;
            $mailer->isHTML(true);
            $mailer->Body = $htmlBody;
            $mailer->AltBody = $textBody ?? strip_tags($htmlBody);

            return $mailer->send();
        } catch (MailException $exception) {
            error_log('MailService error: ' . $exception->getMessage());
            return false;
        }
    }

    /**
     * Send with explicit From/Reply-To (used by TransactionalMailService).
     */
    public function sendWithEnvelope(
        string $fromEmail,
        string $fromName,
        string $toEmail,
        string $toName,
        string $subject,
        string $htmlBody,
        ?string $textBody = null,
        ?string $replyToEmail = null,
        ?string $replyToName = null
    ): bool {
        $mailer = new PHPMailer(true);

        try {
            $mailer->XMailer = ' ';
            $mailer->isSMTP();
            $mailer->Host = $this->config['host'];
            $mailer->Port = (int) $this->config['port'];
            $mailer->SMTPAuth = $this->config['username'] !== '';
            $mailer->Username = $this->config['username'];
            $mailer->Password = $this->config['password'];
            $mailer->CharSet = 'UTF-8';

            $encryption = strtolower($this->config['encryption']);
            if ($encryption === 'tls') {
                $mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            } elseif ($encryption === 'ssl') {
                $mailer->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            }

            $mailer->setFrom($fromEmail, $fromName);
            $mailer->addAddress($toEmail, $toName);
            if ($replyToEmail !== null && $replyToEmail !== '') {
                $mailer->addReplyTo($replyToEmail, $replyToName ?? '');
            }
            $mailer->Subject = $subject;
            $mailer->isHTML(true);
            $mailer->Body = $htmlBody;
            $mailer->AltBody = $textBody ?? strip_tags($htmlBody);

            return $mailer->send();
        } catch (MailException $exception) {
            error_log('MailService error: ' . $exception->getMessage());
            return false;
        }
    }

    private function loadConfig(): array
    {
        $appName = $_ENV['APP_NAME'] ?? getenv('APP_NAME') ?? 'Cartly';
        $appDomain = $_ENV['APP_DOMAIN'] ?? getenv('APP_DOMAIN') ?? 'cartly.test';

        return [
            'host' => $_ENV['SMTP_HOST'] ?? getenv('SMTP_HOST') ?? '',
            'port' => $_ENV['SMTP_PORT'] ?? getenv('SMTP_PORT') ?? 587,
            'username' => $_ENV['SMTP_USERNAME'] ?? getenv('SMTP_USERNAME') ?? '',
            'password' => $_ENV['SMTP_PASSWORD'] ?? getenv('SMTP_PASSWORD') ?? '',
            'encryption' => $_ENV['SMTP_ENCRYPTION'] ?? getenv('SMTP_ENCRYPTION') ?? 'tls',
            'from_address' => $_ENV['SMTP_FROM_ADDRESS'] ?? getenv('SMTP_FROM_ADDRESS') ?? ('no-reply@' . $appDomain),
            'from_name' => $_ENV['SMTP_FROM_NAME'] ?? getenv('SMTP_FROM_NAME') ?? $appName,
        ];
    }
}
