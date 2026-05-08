<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Report extends Model
{
    protected $fillable = [
        'name',
        'type',
        'generated_at',
        'data',
    ];

    protected $casts = [
        'generated_at' => 'datetime',
        'data' => 'array',
        'type' => 'string',
    ];
}
