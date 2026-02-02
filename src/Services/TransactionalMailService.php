<?php

namespace App\Services;

use App\Models\Shop;
use App\Models\ShopEmailSettings;
use Carbon\Carbon;

/**
 * Provider-agnostic transactional mail: resolves From/Reply-To, enforces
 * per-shop limits (from .env), logs send/failure. All sending goes through
 * the configured transport (Brevo today).
 */
class TransactionalMailService
{
    private int $dailyLimit;
    private ?int $monthlyLimit;

    public function __construct(
        private MailResolver $mailResolver,
        private MailService $mailTransport
    ) {
        $daily = $_ENV['EMAIL_DAILY_LIMIT_PER_SHOP'] ?? getenv('EMAIL_DAILY_LIMIT_PER_SHOP');
        $this->dailyLimit = $daily !== '' && $daily !== false ? (int) $daily : 50;
        $monthly = $_ENV['EMAIL_MONTHLY_LIMIT_PER_SHOP'] ?? getenv('EMAIL_MONTHLY_LIMIT_PER_SHOP');
        $this->monthlyLimit = ($monthly !== '' && $monthly !== false) ? (int) $monthly : null;
    }

    /**
     * Send transactional email. When $shop is null (e.g. platform admin),
     * uses app default From and no limit check.
     */
    public function send(
        ?Shop $shop,
        string $toEmail,
        string $toName,
        string $subject,
        string $htmlBody,
        ?string $textBody = null
    ): bool {
        $envelope = $this->mailResolver->resolveFrom($shop);

        if ($shop !== null) {
            $settings = ShopEmailSettings::firstOrCreate(
                ['shop_id' => $shop->id],
                [
                    'email_mode' => ShopEmailSettings::EMAIL_MODE_GLOBAL,
                    'provider' => ShopEmailSettings::PROVIDER_BREVO,
                ]
            );
            $this->maybeResetCounts($settings);
            if (!$this->withinLimits($settings)) {
                $this->logMail('limit_exceeded', $shop->id, $toEmail, $subject, 'Daily or monthly limit exceeded');
                return false;
            }
        }

        $sent = $this->mailTransport->sendWithEnvelope(
            $envelope['from_email'],
            $envelope['from_name'],
            $toEmail,
            $toName,
            $subject,
            $htmlBody,
            $textBody,
            $envelope['reply_to_email'] ?? null,
            $envelope['reply_to_name'] ?? null
        );

        if ($shop !== null && $sent) {
            $settings = ShopEmailSettings::where('shop_id', $shop->id)->first();
            if ($settings) {
                $settings->daily_email_count = ($settings->daily_email_count ?? 0) + 1;
                $settings->monthly_email_count = ($settings->monthly_email_count ?? 0) + 1;
                $settings->last_sent_at = Carbon::now();
                $settings->save();
            }
        }

        if ($sent) {
            $this->logMail('sent', $shop?->id, $toEmail, $subject, null);
        } else {
            $this->logMail('failure', $shop?->id, $toEmail, $subject, 'Send failed');
        }

        return $sent;
    }

    private function maybeResetCounts(ShopEmailSettings $settings): void
    {
        $now = Carbon::now();
        $changed = false;
        if ($settings->last_sent_at === null) {
            return;
        }
        $last = Carbon::parse($settings->last_sent_at);
        if ($last->format('Y-m-d') !== $now->format('Y-m-d')) {
            $settings->daily_email_count = 0;
            $changed = true;
        }
        if ($last->format('Y-m') !== $now->format('Y-m')) {
            $settings->monthly_email_count = 0;
            $changed = true;
        }
        if ($changed) {
            $settings->save();
        }
    }

    private function withinLimits(ShopEmailSettings $settings): bool
    {
        $daily = (int) ($settings->daily_email_count ?? 0);
        if ($daily >= $this->dailyLimit) {
            return false;
        }
        if ($this->monthlyLimit !== null) {
            $monthly = (int) ($settings->monthly_email_count ?? 0);
            if ($monthly >= $this->monthlyLimit) {
                return false;
            }
        }
        return true;
    }

    private function logMail(string $event, ?int $shopId, string $to, string $subject, ?string $reason): void
    {
        $dir = dirname(__DIR__, 2) . '/storage/logs';
        if (!is_dir($dir)) {
            return;
        }
        $line = date('c') . ' ' . $event
            . ' shop_id=' . ($shopId ?? '')
            . ' to=' . $to
            . ' subject=' . str_replace(["\r", "\n"], ' ', $subject)
            . ($reason !== null ? ' reason=' . str_replace(["\r", "\n"], ' ', $reason) : '')
            . "\n";
        @file_put_contents($dir . '/mail.log', $line, FILE_APPEND | LOCK_EX);
    }
}
