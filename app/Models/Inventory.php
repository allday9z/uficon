<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Inventory extends Model
{
    use SoftDeletes;

    protected $table = 'inventory';
    protected $primaryKey = 'inv_id';

    protected $fillable = [
        'pv_id',
        'st_id',
        'qty_available',
        'qty_reserved',
        'qty_damaged',
        'last_counted_at',
    ];

    protected $casts = [
        'qty_available'   => 'integer',
        'qty_reserved'    => 'integer',
        'qty_damaged'     => 'integer',
        'last_counted_at' => 'datetime',
        'created_at'      => 'datetime',
        'updated_at'      => 'datetime',
        'deleted_at'      => 'datetime',
    ];

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'pv_id', 'pv_id');
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'st_id', 'st_id');
    }

    public function getUsableQuantityAttribute(): int
    {
        return $this->qty_available - $this->qty_reserved;
    }

    public function addStock(int $quantity): void
    {
        if ($quantity > 0) {
            $this->increment('qty_available', $quantity);
        }
    }

    public function removeStock(int $quantity): bool
    {
        if ($this->qty_available >= $quantity && $quantity > 0) {
            $this->decrement('qty_available', $quantity);
            return true;
        }
        return false;
    }

    public function reserveStock(int $quantity): bool
    {
        if ($this->qty_available >= $quantity && $quantity > 0) {
            $this->decrement('qty_available', $quantity);
            $this->increment('qty_reserved', $quantity);
            return true;
        }
        return false;
    }

    public function releaseReservation(int $quantity): void
    {
        if ($quantity > 0 && $this->qty_reserved >= $quantity) {
            $this->decrement('qty_reserved', $quantity);
            $this->increment('qty_available', $quantity);
        }
    }

    public function scopeLowStock(\Illuminate\Database\Eloquent\Builder $query, int $threshold = 5): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('qty_available', '<', $threshold);
    }
}
