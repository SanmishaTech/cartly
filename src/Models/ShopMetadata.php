<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShopMetadata extends Model
{
    protected $table = 'shop_metadata';

    protected $fillable = [
        'shop_id',
        'shop_description',
        'address_line1',
        'address_line2',
        'city',
        'state',
        'postal_code',
        'country',
        'logo_path',
        'favicon_path',
        'hero_type',
        'hero_settings',
        'theme',
        'theme_config',
        'social_media_links',
        'home_sections',
        'home_content',
        'footer_content',
        'third_party',
        'oauth_config',
        'topbar_settings',
    ];

    protected $casts = [
        'hero_settings' => 'array',
        'theme_config' => 'array',
        'social_media_links' => 'array',
        'home_sections' => 'array',
        'home_content' => 'array',
        'footer_content' => 'array',
        'third_party' => 'array',
        'oauth_config' => 'array',
        'topbar_settings' => 'array',
    ];

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }
}
