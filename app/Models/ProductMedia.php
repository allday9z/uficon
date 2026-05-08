<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductMedia extends Model
{
    protected $table = 'product_media';
    protected $primaryKey = 'pm_id';

    protected $fillable = [
        'pd_id',
        'pv_id',
        'pm_src',
        'pm_type',
        'pm_position',
        'pm_alt',
        'pm_width',
        'pm_height',
        'pm_aspect_ratio',
        'shopify_media_id',
    ];

    protected $casts = [
        'pm_position'     => 'integer',
        'pm_width'        => 'integer',
        'pm_height'       => 'integer',
        'pm_aspect_ratio' => 'decimal:3',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'pd_id', 'pd_id');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'pv_id', 'pv_id');
    }

    public function isImage(): bool
    {
        return $this->pm_type === 'image';
    }

    public function isVideo(): bool
    {
        return $this->pm_type === 'video';
    }
}
