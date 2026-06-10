<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LobConfig extends Model
{
    protected $table      = 'lob_config';
    protected $primaryKey = 'lc_id';

    protected $fillable = [
        'lc_lob',
        'lc_url_slug',
        'lc_header_image_desktop',
        'lc_header_image_mobile',
        'lc_banner_action',
    ];

    /**
     * Resolve a URL slug to the canonical lc_lob label.
     * Checks DB alias first, then falls back to hardcoded map.
     */
    public static function resolveLob(string $slug): string
    {
        $slug = strtolower(trim($slug));

        // DB lookup: lc_url_slug or lc_lob match
        $config = static::where('lc_url_slug', $slug)
            ->orWhere(fn ($q) => $q->whereRaw('LOWER(lc_lob) = ?', [$slug]))
            ->first();

        if ($config) {
            return $config->lc_lob;
        }

        // Hardcoded fallback
        return match ($slug) {
            'mac'                          => 'Mac',
            'iphone'                       => 'iPhone',
            'ipad'                         => 'iPad',
            'watch', 'apple-watch'         => 'Apple Watch',
            'airpods', 'music'             => 'AirPods',
            'tv', 'appletv', 'apple-tv',
            'tv-home', 'tv-and-home'       => 'Apple TV',
            'accessories'                  => 'Accessories',
            'audio'                        => 'Audio',
            'homepod'                      => 'HomePod',
            default                        => ucfirst($slug),
        };
    }
}
