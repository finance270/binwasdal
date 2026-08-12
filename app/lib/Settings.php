<?php

class Settings
{
    private static array $cache = [];
    private static bool $loaded = false;

    private static function load(): void
    {
        if (self::$loaded) {
            return;
        }
        self::$cache = [];
        foreach (DB::all('SELECT k, v FROM settings') as $r) {
            self::$cache[$r['k']] = $r['v'];
        }
        self::$loaded = true;
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        self::load();
        return self::$cache[$key] ?? $default;
    }

    public static function set(string $key, ?string $value): void
    {
        DB::q('INSERT INTO settings (k, v) VALUES (?, ?) ON DUPLICATE KEY UPDATE v = VALUES(v)', [$key, $value]);
        self::$cache[$key] = $value;
    }

    public static function forget(string $key): void
    {
        DB::q('DELETE FROM settings WHERE k = ?', [$key]);
        unset(self::$cache[$key]);
    }
}
