<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductAddonMap extends Model
{
    protected $table = 'product_addon_map';
    protected $primaryKey = 'pam_id';

    protected $fillable = [
        'pd_id',
        'addon_pd_id',
        'is_required',
    ];

    protected $casts = [
        'is_required' => 'boolean',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'pd_id', 'pd_id');
    }

    public function addonProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'addon_pd_id', 'pd_id');
    }
}
