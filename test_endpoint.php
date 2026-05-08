<?php

use Illuminate\Contracts\Console\Kernel;
use App\Services\JoseService;
use App\Models\ApiToken;
use Illuminate\Support\Facades\Route;

require __DIR__ . '/vendor/autoload.php';

$app = require __DIR__ . '/bootstrap/app.php';

$app->make(Kernel::class)->bootstrap();

// Create or get API Token
$tokenString = 'test-token-12345';
$apiToken = ApiToken::firstOrCreate(
    ['token' => $tokenString],
    ['name' => 'Test Token', 'is_active' => true]
);

if (!$apiToken->is_active) {
    $apiToken->update(['is_active' => true]);
}

$token = $tokenString;

echo "Using Token: " . $token . "\n";

$payload = [
    'request' => 'getAllStore'
];

$appKey = config('app.key');
if (str_starts_with($appKey, 'base64:')) {
    $appKey = base64_decode(substr($appKey, 7));
}

// 1. Sign JWS
$jsonPayload = json_encode($payload);
$jws = JoseService::signJws($jsonPayload, $appKey);

// 2. Encrypt JWE
$jwe = JoseService::encryptJwe($jws, $appKey);

echo "Testing getAllStore...\n";

// 3. Dispatch Request Internally
$request = Illuminate\Http\Request::create(
    '/api/v1/stores/getAllStore',
    'POST',
    ['data' => $jwe]
);
$request->headers->set('Authorization', 'Bearer ' . $token);
$request->headers->set('Accept', 'application/json');

$response = $app->handle($request);

echo "Response Status: " . $response->getStatusCode() . "\n";
echo "Response Body: " . $response->getContent() . "\n";
