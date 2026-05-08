<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    protected $primaryKey = 'oi_id';
    protected $table = 'order_item';

    protected $fillable = [
        'ord_id',
        'pd_id',
        'oi_quantity',
        'oi_price',
    ];

    protected $casts = [
        'oi_quantity' => 'integer',
        'oi_price' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relations
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'ord_id', 'ord_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'pd_id', 'pd_id');
    }

    // Accessors
    public function getLineAmountAttribute(): float
    {
        return $this->oi_quantity * $this->oi_price;
    }

    // Scopes
    public function scopeByOrder(\Illuminate\Database\Eloquent\Builder $query, int $orderId): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('ord_id', $orderId);
    }

    public function scopeByProduct(\Illuminate\Database\Eloquent\Builder $query, int $productId): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('pd_id', $productId);
    }
}
