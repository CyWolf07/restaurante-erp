<?php

namespace App\Support;

class PinCredential
{
    public static function fingerprint(string $pin): string
    {
        $key = (string) config('app.key');
        if ($key === '') {
            throw new \RuntimeException('Configura APP_KEY antes de registrar credenciales PIN.');
        }

        return hash_hmac('sha256', 'restaurant-pin:'.$pin, $key);
    }
}
