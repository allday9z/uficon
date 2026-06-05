<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductCollection extends Model
{
    protected $table = 'product_collection';
    protected $primaryKey = 'pcol_id';

    protected $fillable = [
        'pcol_title',
        'pcol_handle',
        'pcol_rule_column',
        'pcol_rule_relation',
        'pcol_rule_condition',
        'pcol_option_labels',
        'pcol_option_values',
    ];

    protected $casts = [
        'pcol_option_labels' => 'array',
        'pcol_option_values' => 'array',
    ];

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'pcol_id', 'pcol_id');
    }

    /**
     * Returns option labels as indexed array: [1 => 'Case Finish', 2 => 'Band Color', ...]
     */
    public function getOptionLabelsIndexedAttribute(): array
    {
        return array_filter($this->pcol_option_labels ?? []);
    }

    /**
     * Returns option values as indexed array: [1 => ['Silver', 'Gold', ...], ...]
     */
    public function getOptionValuesParsedAttribute(): array
    {
        $parsed = [];
        foreach ($this->pcol_option_values ?? [] as $index => $valueStr) {
            if ($valueStr) {
                $parsed[$index] = explode('|', $valueStr);
            }
        }
        return $parsed;
    }
}
