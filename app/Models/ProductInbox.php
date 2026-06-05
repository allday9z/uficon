<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductInbox extends Model
{
    protected $table = 'product_inbox';
    protected $primaryKey = 'pib_id';
    public $timestamps = false;

    protected $fillable = [
        'pd_id',
        'pib_text',
        'pib_image',
        'pib_position',
    ];

    protected $casts = [
        'pib_position' => 'integer',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'pd_id', 'pd_id');
    }
}
