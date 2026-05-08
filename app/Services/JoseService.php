<?php

namespace App\Services;

class JoseService
{
    protected static function b64urlDecode(string $data): string
    {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $data .= str_repeat('=', 4 - $remainder);
        }
        return base64_decode(strtr($data, '-_', '+/'));
    }

    protected static function b64urlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    public static function signJws(string $payloadJson, string $secret): string
    {
        $header = ['alg' => 'HS256', 'typ' => 'JWT'];
        $headerB64 = self::b64urlEncode(json_encode($header));
        $payloadB64 = self::b64urlEncode($payloadJson);

        $signingInput = $headerB64 . '.' . $payloadB64;
        $signature = hash_hmac('sha256', $signingInput, $secret, true);
        $signatureB64 = self::b64urlEncode($signature);

        return $headerB64 . '.' . $payloadB64 . '.' . $signatureB64;
    }

    public static function verifyJws(string $compactJws, string $secret): array
    {
        $parts = explode('.', $compactJws);
        if (count($parts) !== 3) {
            throw new \RuntimeException('Invalid JWS compact format');
        }
        [$headerB64, $payloadB64, $signatureB64] = $parts;

        $header = json_decode(self::b64urlDecode($headerB64), true);
        if (!is_array($header) || ($header['alg'] ?? null) !== 'HS256') {
            throw new \RuntimeException('Unsupported JWS alg');
        }

        $signingInput = $headerB64 . '.' . $payloadB64;
        $expected = hash_hmac('sha256', $signingInput, $secret, true);
        $actual = self::b64urlDecode($signatureB64);

        if (!hash_equals($expected, $actual)) {
            throw new \RuntimeException('Invalid JWS signature');
        }

        $payloadJson = self::b64urlDecode($payloadB64);
        $payload = json_decode($payloadJson, true);
        if (!is_array($payload)) {
            throw new \RuntimeException('Invalid JWS payload');
        }

        return $payload;
    }

    public static function encryptJwe(string $plaintext, string $key): string
    {
        $header = ['alg' => 'dir', 'enc' => 'A256GCM'];
        $headerB64 = self::b64urlEncode(json_encode($header));

        $iv = random_bytes(12);
        $ivB64 = self::b64urlEncode($iv);

        $aad = $headerB64;
        $tag = '';

        $ciphertext = openssl_encrypt(
            $plaintext,
            'aes-256-gcm',
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            $aad,
            16 // Tag length
        );

        if ($ciphertext === false) {
            throw new \RuntimeException('JWE encryption failed');
        }

        $ciphertextB64 = self::b64urlEncode($ciphertext);
        $tagB64 = self::b64urlEncode($tag);
        $encryptedKeyB64 = '';

        return $headerB64 . '.' . $encryptedKeyB64 . '.' . $ivB64 . '.' . $ciphertextB64 . '.' . $tagB64;
    }

    public static function decryptJwe(string $compactJwe, string $key): string
    {
        $parts = explode('.', $compactJwe);
        if (count($parts) !== 5) {
            throw new \RuntimeException('Invalid JWE compact format');
        }
        [$headerB64, $encryptedKeyB64, $ivB64, $ciphertextB64, $tagB64] = $parts;

        $header = json_decode(self::b64urlDecode($headerB64), true);
        if (!is_array($header) || ($header['alg'] ?? null) !== 'dir' || ($header['enc'] ?? null) !== 'A256GCM') {
            throw new \RuntimeException('Unsupported JWE header');
        }

        $aad = $headerB64;
        $iv = self::b64urlDecode($ivB64);
        $ciphertext = self::b64urlDecode($ciphertextB64);
        $tag = self::b64urlDecode($tagB64);

        $plaintext = openssl_decrypt(
            $ciphertext,
            'aes-256-gcm',
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            $aad
        );

        if ($plaintext === false) {
            throw new \RuntimeException('JWE decryption failed');
        }

        return $plaintext;
    }
}
