<?php

namespace ArctisDev\PlayerCounter\Support;

class PlayerRouteKey
{
    public static function encode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    public static function decode(string $value): ?string
    {
        $decoded = base64_decode(strtr($value, '-_', '+/'), true);

        if (!is_string($decoded) || $decoded === '') {
            return null;
        }

        return $decoded;
    }
}
