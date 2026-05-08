<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use SoftDeletes;

    protected $table = 'product';
    protected $primaryKey = 'pd_id';

    protected $fillable = [
        'brand_id',
        'pd_name',
        'pd_handle',
        'pd_description',
        'pd_type',
        'price',
        'compare_at_price',
        'pd_status',
        'published_at',
        'shopify_product_id',
    ];

    protected $casts = [
        'price'              => 'decimal:2',
        'compare_at_price'   => 'decimal:2',
        'published_at'       => 'datetime',
        'created_at'         => 'datetime',
        'updated_at'         => 'datetime',
        'deleted_at'         => 'datetime',
    ];

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class, 'brand_id', 'brand_id');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class, 'pd_id', 'pd_id');
    }

    public function options(): HasMany
    {
        return $this->hasMany(ProductOption::class, 'pd_id', 'pd_id')->orderBy('po_position');
    }

    public function media(): HasMany
    {
        return $this->hasMany(ProductMedia::class, 'pd_id', 'pd_id')->orderBy('pm_position');
    }

    public function tags(): HasMany
    {
        return $this->hasMany(ProductTag::class, 'pd_id', 'pd_id');
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(
            ProductCategory::class,
            'product_category_map',
            'pd_id', 'pc_id', 'pd_id', 'pc_id'
        )->withTimestamps();
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'pd_id', 'pd_id');
    }

    public function scopeActive(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('pd_status', 'active');
    }

    public function scopeByBrand(\Illuminate\Database\Eloquent\Builder $query, int $brandId): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('brand_id', $brandId);
    }

    public function getFeaturedImageAttribute(): ?string
    {
        return $this->media()->where('pm_type', 'image')->value('pm_src');
    }

    public function getTotalStockAttribute(): int
    {
        return $this->variants()->withSum('inventory', 'qty_available')->get()
            ->sum('inventory_sum_qty_available');
    }

    public function getVariantCountAttribute(): int
    {
        return $this->variants()->count();
    }
}
