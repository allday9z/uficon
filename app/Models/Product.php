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
        'pcol_id',
        'pd_name',
        'pd_primary_title',
        'pd_secondary_title',
        'pd_handle',
        'pd_meta_title',
        'pd_meta_desc',
        'pd_meta_image',
        'pd_lob',
        'pd_sub_lob',
        'pd_description',
        'pd_features',
        'pd_base_name',
        'pd_type',
        'pd_template_type',
        'pd_warranty_parts',
        'pd_warranty_labor',
        'pd_content_sections',
        'pdp_content',
        'pd_overview',
        'price',
        'compare_at_price',
        'pd_status',
        'pd_badge',
        'pd_stripe_gallery_id',
        'published_at',
        'shopify_product_id',
    ];

    protected $casts = [
        'pd_content_sections' => 'array',
        'pdp_content'        => 'array',
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

    public function collection(): BelongsTo
    {
        return $this->belongsTo(ProductCollection::class, 'pcol_id', 'pcol_id');
    }

    public function inbox(): HasMany
    {
        return $this->hasMany(ProductInbox::class, 'pd_id', 'pd_id')->orderBy('pib_position');
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

    public function productLevelMedia(): HasMany
    {
        return $this->hasMany(ProductMedia::class, 'pd_id', 'pd_id')
            ->whereNull('pv_id')
            ->orderBy('pm_position');
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

    public function productRelations(): HasMany
    {
        return $this->hasMany(ProductRelation::class, 'pd_id', 'pd_id')->orderBy('pr_position');
    }

    public function addonMaps(): HasMany
    {
        return $this->hasMany(ProductAddonMap::class, 'pd_id', 'pd_id');
    }

    public function galleries(): HasMany
    {
        return $this->hasMany(ProductGallery::class, 'pd_id', 'pd_id')->orderBy('pg_position');
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
