<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductOption extends Model
{
    protected $table = 'product_option';
    protected $primaryKey = 'po_id';

    protected $fillable = [
        'pd_id',
        'po_name',
        'po_position',
    ];

    protected $casts = [
        'po_position' => 'integer',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'pd_id', 'pd_id');
    }
}
