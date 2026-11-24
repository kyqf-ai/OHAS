<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

class Company
{
    /** 单行记录：无则建空行，恒返回 id */
    public static function row(): array
    {
        $row = Database::row('SELECT * FROM company ORDER BY id LIMIT 1');
        if ($row === null) {
            $now = now_str();
            Database::run('INSERT INTO company (name, created_at, updated_at) VALUES (\'\', ?, ?)', [$now, $now]);
            $row = Database::row('SELECT * FROM company ORDER BY id LIMIT 1');
        }
        return $row;
    }

    public static function name(): string
    {
        return (string)self::row()['name'];
    }

    public static function save(array $data): void
    {
        $row = self::row();
        $data['updated_at'] = now_str();
        $sets = implode(', ', array_map(fn ($c) => "$c = ?", array_keys($data)));
        Database::run('UPDATE company SET ' . $sets . ' WHERE id = ?', array_merge(array_values($data), [$row['id']]));
    }
}
