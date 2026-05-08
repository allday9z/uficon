<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductCategory extends Model
{
    use SoftDeletes;

    protected $table = 'product_category';
    protected $primaryKey = 'pc_id';

    protected $fillable = [
        'pc_name',
        'pc_description',
        'pc_status',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // Relations
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(
            Product::class,
            'product_category_map',
            'pc_id',
            'pd_id',
            'pc_id',
            'pd_id'
        )->withTimestamps();
    }
}
