<?php

namespace App\Services;

use App\Models\Shop;
use App\Models\ShopEmailSettings;

/**
 * Resolves From / Reply-To for transactional email (provider-agnostic).
 * When shop uses custom_domain and has from_email set, use it as From (verified in provider).
 * Otherwise use global sender. Reply-To uses reply_to_email if set, else from_email.
 */
class MailResolver
{
    public function resolveFrom(?Shop $shop): array
    {
        $fromName = 'Cartly';
        $fromEmail = $this->getGlobalFromEmail();
        $replyToEmail = null;
        $replyToName = null;

        if ($shop !== null) {
            $settings = ShopEmailSettings::where('shop_id', $shop->id)->first();
            if ($settings) {
                $useCustomFrom = $settings->email_mode === ShopEmailSettings::EMAIL_MODE_CUSTOM_DOMAIN
                    && $settings->from_email !== null
                    && $settings->from_email !== '';
                if ($useCustomFrom) {
                    $fromEmail = $settings->from_email;
                    $fromName = $settings->from_name !== null && $settings->from_name !== ''
                        ? $settings->from_name
                        : $shop->shop_name;
                } else {
                    $fromName = $shop->shop_name . ' via Cartly';
                }
                $replyToEmail = $settings->reply_to_email !== null && $settings->reply_to_email !== ''
                    ? $settings->reply_to_email
                    : ($settings->from_email ?: null);
                $replyToName = $settings->reply_to_name ?: null;
            } else {
                $fromName = $shop->shop_name . ' via Cartly';
            }
        }

        return [
            'from_email' => $fromEmail,
            'from_name' => $fromName,
            'reply_to_email' => $replyToEmail,
            'reply_to_name' => $replyToName,
        ];
    }

    private function getGlobalFromEmail(): string
    {
        $domain = $_ENV['APP_DOMAIN'] ?? getenv('APP_DOMAIN') ?? 'cartly.test';
        return $_ENV['SMTP_FROM_ADDRESS'] ?? getenv('SMTP_FROM_ADDRESS') ?? ('no-reply@' . $domain);
    }
}
