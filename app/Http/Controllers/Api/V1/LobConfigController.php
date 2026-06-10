<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\LobConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class LobConfigController extends Controller
{
    public function show(string $lob): JsonResponse
    {
        $lobLabel = match (strtolower($lob)) {
            'mac'                      => 'Mac',
            'iphone'                   => 'iPhone',
            'ipad'                     => 'iPad',
            'watch', 'apple-watch'     => 'Apple Watch',
            'airpods', 'music'         => 'AirPods',
            'tv', 'appletv', 'apple-tv',
            'tv-home', 'tv-and-home'   => 'Apple TV',
            'accessories'              => 'Accessories',
            'audio'                    => 'Audio',
            'homepod'                  => 'HomePod',
            default                    => ucfirst($lob),
        };

        $config = LobConfig::where('lc_lob', $lobLabel)->first();

        // Resolve storage path → full URL (FileUpload stores relative path)
        $resolve = function (?string $path): ?string {
            if (! $path) return null;
            if (str_starts_with($path, 'http')) return $path;
            return Storage::disk('public')->url($path);
        };

        return response()->json([
            'lob'                => $lobLabel,
            'headerImageDesktop' => $resolve($config?->lc_header_image_desktop),
            'headerImageMobile'  => $resolve($config?->lc_header_image_mobile),
            'bannerAction'       => $config?->lc_banner_action ?? 'compare',
        ]);
    }
}
