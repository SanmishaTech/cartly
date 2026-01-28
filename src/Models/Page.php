<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Page extends Model
{
    protected $table = 'pages';

    protected $fillable = [
        'shop_id',
        'slug',
        'title',
        'type',
        'content_json',
        'status',
        'show_in_menu',
        'menu_order',
    ];

    protected $casts = [
        'content_json' => 'array',
        'show_in_menu' => 'boolean',
        'menu_order' => 'integer',
    ];

    public function shop()
    {
        return $this->belongsTo(Shop::class);
    }
}
