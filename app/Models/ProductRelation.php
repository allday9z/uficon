<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductRelation extends Model
{
    protected $table = 'product_relation';
    protected $primaryKey = 'pr_id';

    protected $fillable = [
        'pd_id',
        'related_pd_id',
        'pr_type',
        'pr_position',
    ];

    protected $casts = [
        'pr_position' => 'integer',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'pd_id', 'pd_id');
    }

    public function relatedProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'related_pd_id', 'pd_id');
    }
}
