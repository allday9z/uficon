<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductVariant extends Model
{
    use SoftDeletes;

    protected $table = 'product_variant';
    protected $primaryKey = 'pv_id';

    protected $fillable = [
        'pd_id',
        'pv_title',
        'pv_caas_title',
        'pv_sku',
        'pv_mpn',
        'pv_barcode',
        'pv_handle',
        'price',
        'compare_at_price',
        'pv_option1',
        'pv_option2',
        'pv_option3',
        'pv_option4',
        'pv_option5',
        'pv_option6',
        'pv_option7',
        'pv_color_swatch',
        'pv_band_swatch',
        'pv_weight',
        'requires_shipping',
        'taxable',
        'pv_available',
        'is_pickup_available',
        'delivery_lead_time',
        'pg_id',
        'shopify_variant_id',
    ];

    protected $casts = [
        'price'               => 'decimal:2',
        'compare_at_price'    => 'decimal:2',
        'pv_weight'           => 'decimal:3',
        'requires_shipping'   => 'boolean',
        'taxable'             => 'boolean',
        'pv_available'        => 'boolean',
        'is_pickup_available' => 'boolean',
        'created_at'       => 'datetime',
        'updated_at'       => 'datetime',
        'deleted_at'       => 'datetime',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'pd_id', 'pd_id');
    }

    public function inventory(): HasMany
    {
        return $this->hasMany(Inventory::class, 'pv_id', 'pv_id');
    }

    public function media(): HasMany
    {
        return $this->hasMany(ProductMedia::class, 'pv_id', 'pv_id');
    }

    public function gallery(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(ProductGallery::class, 'pg_id', 'pg_id');
    }

    public function getTotalStockAttribute(): int
    {
        return $this->inventory()->sum('qty_available');
    }

    public function getStockAtStore(int $storeId): int
    {
        return $this->inventory()->where('st_id', $storeId)->value('qty_available') ?? 0;
    }
}
