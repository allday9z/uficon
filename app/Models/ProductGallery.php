<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

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

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (empty($model->pg_slug)) {
                $base = $model->pg_name ?? '';
                $model->pg_slug = Str::slug($base) ?: 'gallery-' . substr(md5($base . uniqid()), 0, 8);
            }
        });
    }

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
