<?php

namespace ArctisDev\PlayerCounter\Support;

class PlayerIdentity
{
    /** @param array{id?: mixed, name?: mixed} $player */
    public static function key(array $player): string
    {
        $sourceId = static::normalizeSourceId($player['id'] ?? null, $player['name'] ?? null);
        $name = trim((string) ($player['name'] ?? ''));

        if ($sourceId !== null) {
            return 'id:' . mb_strtolower($sourceId);
        }

        return 'name:' . mb_strtolower($name);
    }

    public static function normalizeSourceId(mixed $sourceId, mixed $name = null): ?string
    {
        $normalizedSourceId = trim((string) $sourceId);
        $normalizedName = trim((string) $name);

        if ($normalizedSourceId === '' || ctype_digit($normalizedSourceId)) {
            return null;
        }

        if ($normalizedName !== '' && mb_strtolower($normalizedSourceId) === mb_strtolower($normalizedName)) {
            return null;
        }

        return $normalizedSourceId;
    }

    public static function nameKey(string $name): string
    {
        return 'name:' . mb_strtolower(trim($name));
    }

    public static function mirroredIdKey(string $name): string
    {
        return 'id:' . mb_strtolower(trim($name));
    }
}
