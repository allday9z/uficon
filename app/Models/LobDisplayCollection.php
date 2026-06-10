<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class LobDisplayCollection extends Model
{
    protected $table      = 'lob_display_collection';
    protected $primaryKey = 'ldc_id';

    protected $fillable = [
        'ldc_lob',
        'ldc_sub_lob',
        'ldc_slug',
        'ldc_title',
        'ldc_badge',
        'ldc_tagline',
        'ldc_description',
        'ldc_hero_image',
        'ldc_hero_detail_href',
        'ldc_hero_buy_href',
        'ldc_stripe_image',
        'ldc_sort_order',
        'ldc_is_featured',
        'ldc_is_active',
    ];

    protected $casts = [
        'ldc_is_featured' => 'boolean',
        'ldc_is_active'   => 'boolean',
        'ldc_sort_order'  => 'integer',
    ];

    /** All active products belonging to this display group. */
    public function products()
    {
        return Product::query()
            ->where('pd_lob', $this->ldc_lob)
            ->where('pd_sub_lob', $this->ldc_sub_lob)
            ->where('pd_status', 'active');
    }

    /** Derive slug from sub_lob string. */
    public static function slugFrom(string $subLob): string
    {
        return Str::slug($subLob);
    }
}
