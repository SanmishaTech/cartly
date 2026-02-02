<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShopEmailSettings extends Model
{
    protected $table = 'shop_email_settings';

    public const EMAIL_MODE_GLOBAL = 'global';
    public const EMAIL_MODE_CUSTOM_DOMAIN = 'custom_domain';
    public const PROVIDER_BREVO = 'brevo';
    public const PROVIDER_SES = 'ses';

    protected $fillable = [
        'shop_id',
        'email_mode',
        'from_name',
        'from_email',
        'reply_to_email',
        'reply_to_name',
        'domain',
        'domain_verified',
        'provider',
        'daily_email_count',
        'monthly_email_count',
        'last_sent_at',
    ];

    protected $casts = [
        'domain_verified' => 'boolean',
        'last_sent_at' => 'datetime',
    ];

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }
}
