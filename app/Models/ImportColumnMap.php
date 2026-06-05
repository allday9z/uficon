<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportColumnMap extends Model
{
    protected $primaryKey = 'icm_id';

    protected $fillable = [
        'ip_id',
        'icm_source_header',
        'icm_source_index',
        'icm_target_model',
        'icm_target_field',
        'icm_default_value',
        'icm_required',
        'icm_update_mode',
        'icm_cast',
        'icm_position',
    ];

    protected $casts = [
        'icm_source_index' => 'integer',
        'icm_required'     => 'boolean',
        'icm_position'     => 'integer',
    ];

    public function profile(): BelongsTo
    {
        return $this->belongsTo(ImportProfile::class, 'ip_id', 'ip_id');
    }

    /** Resolve value from a raw row array using index or null */
    public function resolveFrom(array $row): mixed
    {
        $raw = null;

        if ($this->icm_source_index !== null && isset($row[$this->icm_source_index])) {
            $raw = $row[$this->icm_source_index];
        }

        if ($raw === null || $raw === '') {
            $raw = $this->icm_default_value;
        }

        if ($raw === null) {
            return null;
        }

        return $this->castValue((string) $raw);
    }

    private function castValue(string $value): mixed
    {
        return match ($this->icm_cast) {
            'int'   => (int) $value,
            'float' => (float) $value,
            'bool'  => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'slug'  => \Illuminate\Support\Str::slug($value),
            'json'  => json_decode($value, true),
            default => trim($value) === '' ? null : trim($value),
        };
    }
}
