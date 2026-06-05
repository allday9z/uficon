<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ImportProfile extends Model
{
    protected $primaryKey = 'ip_id';

    protected $fillable = [
        'ip_name',
        'ip_slug',
        'ip_sheet_name',
        'ip_header_row',
        'ip_description',
    ];

    protected $casts = [
        'ip_header_row' => 'integer',
    ];

    public function columnMaps(): HasMany
    {
        return $this->hasMany(ImportColumnMap::class, 'ip_id', 'ip_id')
                    ->orderBy('icm_position');
    }

    /** Return maps keyed by target_model, then target_field for fast lookup */
    public function mapsGrouped(): array
    {
        return $this->columnMaps
            ->groupBy('icm_target_model')
            ->map(fn ($group) => $group->keyBy('icm_target_field'))
            ->toArray();
    }
}
