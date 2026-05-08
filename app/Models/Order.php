<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use SoftDeletes;

    protected $table = 'order';
    protected $primaryKey = 'ord_id';

    protected $fillable = [
        'st_id',
        'ord_customer_name',
        'ord_total_amount',
        'ord_status',
        'ord_date',
    ];

    protected $casts = [
        'ord_total_amount' => 'decimal:2',
        'ord_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // Relations
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'st_id', 'st_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'ord_id', 'ord_id');
    }

    // Scopes
    public function scopeByStore(\Illuminate\Database\Eloquent\Builder $query, int $storeId): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('st_id', $storeId);
    }

    public function scopeByStatus(\Illuminate\Database\Eloquent\Builder $query, string $status): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('ord_status', $status);
    }

    public function scopePending(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('ord_status', 'pending');
    }

    public function scopeCompleted(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('ord_status', 'completed');
    }
}
