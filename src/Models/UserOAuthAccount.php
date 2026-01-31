<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserOAuthAccount extends Model
{
    protected $table = 'user_oauth_accounts';

    protected $fillable = [
        'user_id',
        'provider',
        'provider_user_id',
        'email',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
