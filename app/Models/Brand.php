<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Brand extends Model
{
    use SoftDeletes;

    protected $table = 'brand';
    protected $primaryKey = 'brand_id';

    protected $fillable = [
        'brand_name',
        'brand_code',
        'brand_icon',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // Relations
    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'brand_id', 'brand_id');
    }

    public function stores(): HasMany
    {
        return $this->hasMany(Store::class, 'brand_id', 'brand_id');
    }
}
