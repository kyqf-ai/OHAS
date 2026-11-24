<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

class Setting
{
    public static function get(string $key, string $default = ''): string
    {
        $v = Database::value('SELECT value FROM settings WHERE key = ?', [$key]);
        return $v === null ? $default : (string)$v;
    }

    public static function set(string $key, string $value): void
    {
        Database::run(
            'INSERT INTO settings (key, value) VALUES (?, ?) ON CONFLICT(key) DO UPDATE SET value = excluded.value',
            [$key, $value]
        );
    }
}
