<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Package extends Model
{
    protected $table = 'packages';
    protected $fillable = [
        'name',
        'cost_1_month',
        'cost_3_month',
        'cost_6_month',
        'cost_12_month',
        'features',
        'active'
    ];
    protected $casts = [
        'cost_1_month' => 'decimal:2',
        'cost_3_month' => 'decimal:2',
        'cost_6_month' => 'decimal:2',
        'cost_12_month' => 'decimal:2',
        'features' => 'array',
        'active' => 'boolean',
    ];
}
