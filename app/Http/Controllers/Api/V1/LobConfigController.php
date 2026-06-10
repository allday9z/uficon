<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\LobConfig;
use Illuminate\Http\JsonResponse;

class LobConfigController extends Controller
{
    public function show(string $lob): JsonResponse
    {
        $lobLabel = match (strtolower($lob)) {
            'mac' => 'Mac', 'iphone' => 'iPhone', 'ipad' => 'iPad',
            'watch', 'apple-watch' => 'Apple Watch', 'airpods' => 'AirPods',
            default => ucfirst($lob),
        };

        $config = LobConfig::where('lc_lob', $lobLabel)->first();

        return response()->json([
            'lob'                => $lobLabel,
            'headerImageDesktop' => $config?->lc_header_image_desktop,
            'headerImageMobile'  => $config?->lc_header_image_mobile,
            'bannerAction'       => $config?->lc_banner_action ?? 'compare',
        ]);
    }
}
