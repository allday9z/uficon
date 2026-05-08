<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Store extends Model
{
    protected $table = 'store';
    protected $primaryKey = 'st_id';
    
    protected $fillable = [
        'brand_id',
        'st_name',
        'st_address',
        'st_full_address',
        'st_code',
        'st_phone',
        'st_contact_links',
        'google_map_url',
        'images',
        'latitude',
        'longitude',
        'st_is_active'
    ];

    protected $casts = [
        'images' => 'array',
        'st_phone' => 'array',
        'st_contact_links' => 'array',
        'st_is_active' => 'boolean',
        'latitude' => 'float',
        'longitude' => 'float',
    ];

    protected $appends = [
        'location',
    ];

    public function getLocationAttribute(): array
    {
        return [
            "lat" => (float)$this->latitude,
            "lng" => (float)$this->longitude,
        ];
    }

    public function setLocationAttribute(?array $location): void
    {
        if (is_array($location)) {
            $this->attributes['latitude'] = $location['lat'];
            $this->attributes['longitude'] = $location['lng'];
            unset($this->attributes['location']);
        }
    }

    public function brand()
    {
        return $this->belongsTo(Brand::class, 'brand_id', 'brand_id');
    }

    public function inventory()
    {
        return $this->hasMany(Inventory::class, 'st_id', 'st_id');
    }

    public function orders()
    {
        return $this->hasMany(Order::class, 'st_id', 'st_id');
    }
}
