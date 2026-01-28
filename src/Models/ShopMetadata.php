<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShopMetadata extends Model
{
    protected $table = 'shop_metadata';

    protected $fillable = [
        'shop_id',
        'social_media_links',
        'home_sections',
        'home_content',
        'footer_content',
        'third_party',
    ];

    protected $casts = [
        'social_media_links' => 'array',
        'home_sections' => 'array',
        'home_content' => 'array',
        'footer_content' => 'array',
        'third_party' => 'array',
    ];
}
