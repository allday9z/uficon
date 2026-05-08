<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApiLog extends Model
{
    protected $fillable = [
        'api_token_id',
        'method',
        'url',
        'payload',
        'response',
        'status_code',
        'ip_address',
        'duration_ms',
    ];

    protected $casts = [
        'payload' => 'array',
        'response' => 'array',
    ];

    public function apiToken()
    {
        return $this->belongsTo(ApiToken::class);
    }
}
