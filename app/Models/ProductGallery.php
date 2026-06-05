<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductGallery extends Model
{
    protected $table = 'product_gallery';
    protected $primaryKey = 'pg_id';

    protected $fillable = [
        'pd_id',
        'pg_name',
        'pg_slug',
        'pg_position',
    ];

    protected $casts = [
        'pg_position' => 'integer',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'pd_id', 'pd_id');
    }

    public function media(): HasMany
    {
        return $this->hasMany(ProductMedia::class, 'pg_id', 'pg_id')->orderBy('pm_position');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class, 'pg_id', 'pg_id');
    }
}
